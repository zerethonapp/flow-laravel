<?php

namespace Tests\Support;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A FormRequest whose rules() reads real request/route state — cannot be
 * safely evaluated on a constructor-skipped instance. Used to prove
 * RouteDiscovery degrades to a null rules() instead of throwing.
 */
class UnsafeRulesRequest extends FormRequest
{
    public function rules(): array
    {
        // input() touches Symfony\Request's internal $request/$query
        // property bags, which are uninitialized typed properties on a
        // constructor-skipped instance — accessing them throws an Error.
        // This is exactly the case RouteDiscovery must survive without
        // crashing the whole route's discovery entry.
        $context = $this->input('context');

        return [
            'id' => ['required', 'exists:notes,'.$context],
        ];
    }
}
