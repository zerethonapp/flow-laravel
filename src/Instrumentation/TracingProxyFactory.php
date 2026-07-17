<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation;

use ArchonFlow\Laravel\Support\Traceable;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Throwable;

/**
 * Builds a runtime subclass proxy that times every public method call and
 * forwards it to the real implementation, so a service class needs zero
 * manual instrumentation to show up as a 'service' node.
 *
 * Only classes resolved through the container (see ServiceAutoTraceRegistrar)
 * are proxied. If a class cannot be safely proxied (final, weird signature,
 * etc.) wrap() falls back to returning the original instance untouched.
 */
final class TracingProxyFactory
{
    /** @var array<string, string|null> original class => generated proxy class (null = ineligible) */
    private static array $generated = [];

    public static function wrap(object $instance, TraceCollector $collector): object
    {
        $class = $instance::class;

        try {
            $proxyClass = self::$generated[$class] ??= self::generate($class);

            if ($proxyClass === null) {
                return $instance;
            }

            /** @var object&array{__archonCollector: TraceCollector, __archonLabelPrefix: string} $proxy */
            $proxy = (new ReflectionClass($proxyClass))->newInstanceWithoutConstructor();
            self::copyState($instance, $proxy, $class);

            $proxy->__archonCollector = $collector;
            $proxy->__archonLabelPrefix = class_basename($class);

            return $proxy;
        } catch (Throwable) {
            self::$generated[$class] = null;

            return $instance;
        }
    }

    private static function generate(string $class): ?string
    {
        $reflection = new ReflectionClass($class);

        if ($reflection->isFinal() || $reflection->isAbstract() || $reflection->isInterface() || $reflection->isEnum()) {
            return null;
        }

        // Classes that already self-instrument via the Traceable trait (or
        // Archon::trace() inside a Traceable method) would otherwise get
        // double-wrapped: one span from this proxy, one from their own
        // manual call, both with the same label.
        if (in_array(Traceable::class, class_uses_recursive($class), true)) {
            return null;
        }

        // Eloquent models rely on late static binding (`static::class`
        // inside inherited framework methods like newQuery()/newInstance())
        // for correct query building and result hydration. Swapping in a
        // dynamically generated subclass would silently break that — this
        // is a correctness guard, not a noise-reduction one, so it applies
        // regardless of trace_namespaces/trace_namespace_excludes config.
        if (is_subclass_of($class, Model::class)) {
            return null;
        }

        $methods = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isFinal() || $method->isAbstract() || $method->isConstructor()) {
                continue;
            }
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }
            $methods[] = $method;
        }

        if ($methods === []) {
            return null;
        }

        $proxyClass = 'ArchonTraceProxy_' . str_replace('\\', '_', $class) . '_' . substr(md5($class), 0, 8);

        if (class_exists($proxyClass, false)) {
            return $proxyClass;
        }

        eval(self::buildSource($proxyClass, $class, $methods));

        return $proxyClass;
    }

    /**
     * @param array<int, ReflectionMethod> $methods
     */
    private static function buildSource(string $proxyClass, string $class, array $methods): string
    {
        $source = "final class {$proxyClass} extends \\{$class} {\n";
        $source .= "    public \\ArchonFlow\\Laravel\\Instrumentation\\TraceCollector \$__archonCollector;\n";
        $source .= "    public string \$__archonLabelPrefix = '';\n";

        foreach ($methods as $method) {
            $source .= self::buildMethod($method);
        }

        $source .= "}\n";

        return $source;
    }

    private static function buildMethod(ReflectionMethod $method): string
    {
        $name = $method->getName();
        $params = self::buildParameterList($method);
        $forward = self::buildForwardList($method);
        $returnType = self::buildReturnType($method);
        $returns = $returnType === ': void' ? '' : 'return ';

        return <<<PHP
    public function {$name}({$params}){$returnType}
    {
        \$__span = \$this->__archonCollector->beginServiceSpan(\$this->__archonLabelPrefix . '.{$name}');
        try {
            {$returns}parent::{$name}({$forward});
        } finally {
            \$this->__archonCollector->endServiceSpan(\$__span);
        }
    }

PHP;
    }

    private static function buildParameterList(ReflectionMethod $method): string
    {
        $parts = [];

        foreach ($method->getParameters() as $parameter) {
            $part = '';

            $type = self::typeToString($parameter->getType());
            if ($type !== null) {
                $part .= $type . ' ';
            }

            if ($parameter->isPassedByReference()) {
                $part .= '&';
            }

            if ($parameter->isVariadic()) {
                $part .= '...';
            }

            $part .= '$' . $parameter->getName();

            if (!$parameter->isVariadic() && $parameter->isDefaultValueAvailable()) {
                $part .= ' = ' . self::defaultValueLiteral($parameter);
            }

            $parts[] = $part;
        }

        return implode(', ', $parts);
    }

    private static function buildForwardList(ReflectionMethod $method): string
    {
        $parts = [];

        foreach ($method->getParameters() as $parameter) {
            $name = '$' . $parameter->getName();
            $parts[] = $parameter->isVariadic() ? "...{$name}" : $name;
        }

        return implode(', ', $parts);
    }

    private static function buildReturnType(ReflectionMethod $method): string
    {
        $type = self::typeToString($method->getReturnType());

        return $type === null ? '' : ": {$type}";
    }

    private static function typeToString(mixed $type): ?string
    {
        return $type === null ? null : (string) $type;
    }

    private static function defaultValueLiteral(ReflectionParameter $parameter): string
    {
        if ($parameter->isDefaultValueConstant()) {
            return (string) $parameter->getDefaultValueConstantName();
        }

        return var_export($parameter->getDefaultValue(), true);
    }

    private static function copyState(object $source, object $target, string $class): void
    {
        $reflection = new ReflectionClass($class);

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);

            if (!$property->isInitialized($source)) {
                continue;
            }

            $property->setValue($target, $property->getValue($source));
        }
    }
}
