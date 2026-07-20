<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zerethon\Flow\Laravel\Instrumentation\Masker;

class MaskerTest extends TestCase
{
    /** @test */
    public function it_masks_sensitive_query_string_values_and_leaves_others_alone()
    {
        $url = 'https://example.com/reset?token=abc123&plan=pro';

        $this->assertSame(
            'https://example.com/reset?token=%2A%2A%2A%2A&plan=pro',
            Masker::maskUrl($url),
        );
    }

    /** @test */
    public function it_leaves_a_url_with_no_query_string_untouched()
    {
        $this->assertSame('https://example.com/dashboard', Masker::maskUrl('https://example.com/dashboard'));
    }

    /** @test */
    public function it_masks_a_sensitive_route_parameter_embedded_in_the_path()
    {
        $uri = '/reset-password/abc123secret';

        $masked = Masker::maskRequestUri($uri, ['token' => 'abc123secret']);

        $this->assertSame('/reset-password/****', $masked);
    }

    /** @test */
    public function it_does_not_mask_a_non_sensitive_route_parameter()
    {
        $uri = '/orders/42';

        $masked = Masker::maskRequestUri($uri, ['id' => '42']);

        $this->assertSame('/orders/42', $masked);
    }

    /** @test */
    public function it_masks_sensitive_keyed_array_values_entirely()
    {
        $masked = Masker::maskArray([
            'password' => 'hunter2',
            'apiKey' => 'sk_live_abc',
            'api_key' => 'sk_live_def',
            'note' => 'unrelated value',
        ]);

        $this->assertSame('****', $masked['password']);
        $this->assertSame('****', $masked['apiKey']);
        $this->assertSame('****', $masked['api_key']);
        $this->assertSame('unrelated value', $masked['note']);
    }

    /** @test */
    public function it_recurses_into_nested_arrays()
    {
        $masked = Masker::maskArray([
            'auth' => ['token' => 'abc', 'user' => 'jane'],
        ]);

        $this->assertSame('****', $masked['auth']['token']);
        $this->assertSame('jane', $masked['auth']['user']);
    }

    /** @test */
    public function it_masks_the_value_of_a_url_key_via_query_string_masking_not_a_blanket_mask()
    {
        $masked = Masker::maskArray([
            'method' => 'GET',
            'url' => 'https://api.example.com/data?api_key=secret123&format=json',
        ]);

        $this->assertSame(
            'https://api.example.com/data?api_key=%2A%2A%2A%2A&format=json',
            $masked['url'],
        );
    }

    /** @test */
    public function it_masks_email_shaped_string_values()
    {
        $this->assertSame('j***@example.com', Masker::maskString('jane@example.com'));
    }

    /** @test */
    public function it_leaves_non_email_strings_unchanged()
    {
        $this->assertSame('just some text', Masker::maskString('just some text'));
    }
}
