<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zerethon\Flow\Laravel\Discovery\ValidationNormalizer;

class ValidationNormalizerTest extends TestCase
{
    private ValidationNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ValidationNormalizer();
    }

    /** @test */
    public function it_normalizes_array_syntax_rules()
    {
        $fields = $this->normalizer->normalize([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $this->assertSame([
            ['name' => 'title', 'type' => 'string', 'required' => true, 'nullable' => false, 'format' => null],
        ], $fields);
    }

    /** @test */
    public function it_normalizes_pipe_delimited_string_syntax_rules()
    {
        $fields = $this->normalizer->normalize([
            'age' => 'nullable|integer|min:0',
        ]);

        $this->assertSame([
            ['name' => 'age', 'type' => 'integer', 'required' => false, 'nullable' => true, 'format' => null],
        ], $fields);
    }

    /** @test */
    public function it_infers_email_format_and_still_types_it_as_string()
    {
        $fields = $this->normalizer->normalize(['email' => ['required', 'email']]);

        $this->assertSame('string', $fields[0]['type']);
        $this->assertSame('email', $fields[0]['format']);
        $this->assertTrue($fields[0]['required']);
    }

    /** @test */
    public function it_infers_uuid_and_url_formats()
    {
        $fields = $this->normalizer->normalize([
            'id' => ['uuid'],
            'website' => ['url'],
        ]);

        $this->assertSame('uuid', $fields[0]['format']);
        $this->assertSame('url', $fields[1]['format']);
    }

    /** @test */
    public function it_infers_boolean_array_and_file_types()
    {
        $fields = $this->normalizer->normalize([
            'active' => ['boolean'],
            'tags' => ['array'],
            'avatar' => ['file'],
        ]);

        $this->assertSame('boolean', $fields[0]['type']);
        $this->assertSame('array', $fields[1]['type']);
        $this->assertSame('file', $fields[2]['type']);
    }

    /** @test */
    public function it_marks_conditional_required_variants_as_not_required_rather_than_misreporting()
    {
        // required_if/required_with etc. are NOT unconditionally required —
        // marking them required:true would overclaim. They're simply not
        // represented as true, not silently dropped from the field list.
        $fields = $this->normalizer->normalize([
            'reason' => ['required_if:status,rejected', 'string'],
        ]);

        $this->assertSame('reason', $fields[0]['name']);
        $this->assertFalse($fields[0]['required']);
    }

    /** @test */
    public function it_defaults_to_unknown_type_when_nothing_recognizable_is_present()
    {
        $fields = $this->normalizer->normalize(['x' => ['in:a,b,c']]);

        $this->assertSame('unknown', $fields[0]['type']);
    }

    /** @test */
    public function it_skips_non_string_rule_entries_without_crashing()
    {
        // A real Rule::in([...]) object would appear here in production —
        // simulate with a plain object standing in for "not a string".
        $fields = $this->normalizer->normalize([
            'status' => ['required', new \stdClass()],
        ]);

        $this->assertSame('status', $fields[0]['name']);
        $this->assertTrue($fields[0]['required']);
    }
}
