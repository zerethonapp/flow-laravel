<?php

namespace Tests\Support;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A FormRequest whose rules() has no dependency on runtime request state —
 * safe to call on a constructor-skipped (reflection-only) instance.
 */
class DemoStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }
}
