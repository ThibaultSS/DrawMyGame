<?php

namespace App\Http\Requests;

use App\Models\DrawingVote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'integer', Rule::in([DrawingVote::LIKE, DrawingVote::DISLIKE])],
        ];
    }
}
