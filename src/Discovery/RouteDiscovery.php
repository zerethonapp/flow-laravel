<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Discovery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Application Discovery: reads Laravel's own route table and reflects each
 * action's signature so Flow can learn what an app looks like without ever
 * sending it a request — the one capability the rest of this package
 * deliberately doesn't have (everything else here only learns about a route
 * once real traffic hits it).
 *
 * Deliberately reflection-only: never resolves a controller or FormRequest
 * through the container (`app()->make()`), since constructing an arbitrary
 * application class can run constructor side effects. This mirrors the
 * caution `TracingProxyFactory` already takes around Eloquent models,
 * applied here to the whole discovery path.
 *
 * Output follows the Application Discovery Contract (see
 * `flow-docs/ADAPTER_PROTOCOL.md`, v1.1): a `Core` layer that's identical in
 * shape whatever framework a future adapter targets, plus a
 * `framework.laravel.*` layer for everything that's genuinely Laravel-
 * specific. A Dashboard (or any consumer) can render a fully useful view
 * reading Core alone.
 */
final class RouteDiscovery
{
    private const RELATIONSHIP_TYPES = [
        'HasMany', 'HasOne', 'BelongsTo', 'BelongsToMany',
        'HasManyThrough', 'HasOneThrough',
        'MorphMany', 'MorphOne', 'MorphTo', 'MorphToMany',
    ];

    public function __construct(
        private readonly ValidationNormalizer $validationNormalizer = new ValidationNormalizer(),
        private readonly PayloadExampleGenerator $payloadGenerator = new PayloadExampleGenerator(),
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function discover(Router $router): array
    {
        $routes = [];

        foreach ($router->getRoutes() as $route) {
            $routes[] = $this->describeRoute($route);
        }

        return $routes;
    }

    /**
     * @return array<string, mixed>
     */
    private function describeRoute(Route $route): array
    {
        [$controllerClass, $controllerMethod] = $this->parseAction($route);
        $middleware = array_values($route->gatherMiddleware());
        $parameters = $route->parameterNames();

        $formRequestClass = $this->findFormRequestClass($controllerClass, $controllerMethod);
        $rawRules = $formRequestClass !== null ? $this->readRulesWithoutConstructing($formRequestClass) : null;
        $fields = $rawRules !== null ? $this->validationNormalizer->normalize($rawRules) : null;

        $modelBinding = $controllerClass !== null && $controllerMethod !== null
            ? $this->describeModelBinding($controllerClass, $controllerMethod, $parameters)
            : [];

        return [
            // --- Core (framework-independent) ---
            'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
            'uri' => $route->uri(),
            'parameters' => $parameters,
            'validation' => $fields !== null ? ['fields' => $fields] : null,
            'authentication' => $this->describeAuthentication($middleware),
            'payload' => $fields !== null ? $this->payloadGenerator->generate($fields) : null,
            'risk' => $this->riskFor($route->methods()),

            // --- Framework Metadata (optional, Laravel-specific) ---
            'framework' => [
                'laravel' => [
                    'routeName' => $route->getName(),
                    'action' => [
                        'controller' => $controllerClass,
                        'method' => $controllerMethod,
                    ],
                    'middleware' => $middleware,
                    'prefix' => $route->getPrefix(),
                    'domain' => $route->getDomain(),
                    // No public accessor exists on modern Laravel's Route
                    // for route-group namespacing (largely unused since
                    // Laravel 8) — always null, kept for forward
                    // compatibility rather than omitted.
                    'namespace' => null,
                    'formRequest' => $formRequestClass,
                    'rules' => $rawRules,
                    'modelBinding' => $modelBinding,
                    'relationships' => $this->describeRelationships($modelBinding),
                    'policies' => $this->describePolicies($middleware),
                ],
            ],
        ];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function parseAction(Route $route): array
    {
        $action = $route->getActionName();

        if ($action === 'Closure' || !str_contains($action, '@')) {
            return [null, null];
        }

        [$controllerClass, $controllerMethod] = explode('@', $action, 2);

        return [$controllerClass, $controllerMethod];
    }

    private function findFormRequestClass(?string $controllerClass, ?string $controllerMethod): ?string
    {
        $method = $this->reflectControllerMethod($controllerClass, $controllerMethod);
        if ($method === null) {
            return null;
        }

        foreach ($method->getParameters() as $parameter) {
            $typeClass = $this->namedParameterType($parameter);
            if ($typeClass !== null && is_subclass_of($typeClass, FormRequest::class)) {
                return $typeClass;
            }
        }

        return null;
    }

    /**
     * @param  string[]  $routeParameters
     * @return array<int, array{parameter: string, model: string, routeParam: string}>
     */
    private function describeModelBinding(string $controllerClass, string $controllerMethod, array $routeParameters): array
    {
        $method = $this->reflectControllerMethod($controllerClass, $controllerMethod);
        if ($method === null) {
            return [];
        }

        $bindings = [];
        foreach ($method->getParameters() as $parameter) {
            $typeClass = $this->namedParameterType($parameter);
            $paramName = $parameter->getName();

            if ($typeClass === null || !is_subclass_of($typeClass, Model::class)) {
                continue;
            }

            if (!in_array($paramName, $routeParameters, true)) {
                continue;
            }

            $bindings[] = [
                'parameter' => $paramName,
                'model' => $typeClass,
                'routeParam' => $paramName,
            ];
        }

        return $bindings;
    }

    /**
     * Best-effort: only relationship methods with a declared return type
     * are visible here — a relationship method without one (common in
     * older/looser codebases) is simply not reported, not misreported.
     *
     * @param  array<int, array{parameter: string, model: string, routeParam: string}>  $modelBindings
     * @return array<int, array{model: string, name: string, type: string}>
     */
    private function describeRelationships(array $modelBindings): array
    {
        $relationships = [];

        foreach ($modelBindings as $binding) {
            $modelClass = $binding['model'];
            if (!class_exists($modelClass)) {
                continue;
            }

            $reflection = new ReflectionClass($modelClass);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $modelClass) {
                    continue; // skip inherited Model:: base methods
                }

                $returnType = $method->getReturnType();
                if (!$returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
                    continue;
                }

                $shortName = class_basename($returnType->getName());
                if (in_array($shortName, self::RELATIONSHIP_TYPES, true)) {
                    $relationships[] = [
                        'model' => $modelClass,
                        'name' => $method->getName(),
                        'type' => $shortName,
                    ];
                }
            }
        }

        return $relationships;
    }

    /**
     * Heuristic mapping from Laravel guard middleware to the contract's
     * normalized `strategies` — see ADAPTER_PROTOCOL.md's Limitations
     * section for exactly what an unrecognized guard name does (nothing;
     * `required` stays correct, `strategies` just won't include it).
     *
     * @param  string[]  $middleware
     * @return array{required: bool, strategies: string[]}
     */
    private function describeAuthentication(array $middleware): array
    {
        $required = false;
        $strategies = [];

        foreach ($middleware as $entry) {
            if ($entry === 'guest' || str_starts_with($entry, 'guest:')) {
                $required = false;
                continue;
            }

            if ($entry === 'auth' || str_starts_with($entry, 'auth:') || $entry === 'auth.basic') {
                $required = true;
            }

            if ($entry === 'auth:sanctum' || $entry === 'auth:api') {
                $strategies[] = 'bearer';
            } elseif ($entry === 'auth' || $entry === 'auth:web') {
                $strategies[] = 'session';
            } elseif ($entry === 'auth.basic') {
                $strategies[] = 'basic';
            }
        }

        return [
            'required' => $required,
            'strategies' => array_values(array_unique($strategies)),
        ];
    }

    /**
     * @param  string[]  $middleware
     * @return string[]
     */
    private function describePolicies(array $middleware): array
    {
        $policies = [];

        foreach ($middleware as $entry) {
            if (str_starts_with($entry, 'can:')) {
                $policies[] = substr($entry, 4);
            }
        }

        return $policies;
    }

    /**
     * Derived, not discovered — guidance only, never a security guarantee.
     *
     * @param  string[]  $methods
     */
    private function riskFor(array $methods): string
    {
        $rank = ['GET' => 0, 'HEAD' => 0, 'OPTIONS' => 0, 'POST' => 1, 'PUT' => 2, 'PATCH' => 2, 'DELETE' => 3];

        $highest = 0;
        foreach ($methods as $method) {
            $highest = max($highest, $rank[$method] ?? 1);
        }

        return match ($highest) {
            0 => 'low',
            1 => 'medium',
            2 => 'high',
            default => 'critical',
        };
    }

    private function reflectControllerMethod(?string $controllerClass, ?string $controllerMethod): ?ReflectionMethod
    {
        if ($controllerClass === null || $controllerMethod === null) {
            return null;
        }

        if (!class_exists($controllerClass) || !method_exists($controllerClass, $controllerMethod)) {
            return null;
        }

        return new ReflectionMethod($controllerClass, $controllerMethod);
    }

    private function namedParameterType(\ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        return $type->getName();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readRulesWithoutConstructing(string $formRequestClass): ?array
    {
        try {
            /** @var FormRequest $instance */
            $instance = (new ReflectionClass($formRequestClass))->newInstanceWithoutConstructor();
            $rules = $instance->rules();

            return is_array($rules) ? $rules : null;
        } catch (Throwable) {
            // Some FormRequest::rules() implementations read $this->route()/
            // ->user()/->input(), none of which exist on a
            // constructor-skipped instance. Degrade to null rather than
            // failing the whole route's discovery entry over one field.
            return null;
        }
    }
}
