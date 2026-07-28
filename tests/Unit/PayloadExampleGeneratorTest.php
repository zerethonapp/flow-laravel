<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zerethon\Flow\Laravel\Discovery\PayloadExampleGenerator;
use Zerethon\Flow\Laravel\Discovery\ValidationNormalizer;

class PayloadExampleGeneratorTest extends TestCase
{
    private PayloadExampleGenerator $generator;
    private ValidationNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->generator = new PayloadExampleGenerator();
        $this->normalizer = new ValidationNormalizer();
    }

    /** @test */
    public function it_generates_representative_placeholder_values_per_type()
    {
        $fields = $this->normalizer->normalize([
            'title' => ['required', 'string'],
            'age' => ['required', 'integer'],
            'active' => ['required', 'boolean'],
            'tags' => ['required', 'array'],
        ]);

        $this->assertSame([
            'title' => 'string',
            'age' => 0,
            'active' => false,
            'tags' => [],
        ], $this->generator->generate($fields));
    }

    /** @test */
    public function it_uses_recognizable_placeholders_for_known_formats()
    {
        $fields = $this->normalizer->normalize([
            'email' => ['required', 'email'],
            'id' => ['required', 'uuid'],
            'website' => ['required', 'url'],
        ]);

        $this->assertSame([
            'email' => 'user@example.com',
            'id' => '00000000-0000-0000-0000-000000000000',
            'website' => 'https://example.com',
        ], $this->generator->generate($fields));
    }

    /** @test */
    public function a_nullable_non_required_field_gets_null_instead_of_a_typed_placeholder()
    {
        $fields = $this->normalizer->normalize(['bio' => ['nullable', 'string']]);

        $this->assertNull($this->generator->generate($fields)['bio']);
    }

    /** @test */
    public function this_is_only_ever_a_template_never_executed_or_submitted()
    {
        // Documentation-as-test: generate() is a pure function over already-
        // normalized field descriptors — it never touches the network, the
        // container, or the FormRequest class itself.
        $fields = $this->normalizer->normalize(['title' => ['required', 'string']]);
        $payload = $this->generator->generate($fields);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('title', $payload);
    }
}
