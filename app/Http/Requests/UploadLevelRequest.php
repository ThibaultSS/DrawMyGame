<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadLevelRequest extends FormRequest
{
    /**
     * Anyone may upload a level — playing does not require an account.
     */
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
            // File::image() checks the actual file content, not just the
            // extension, and excludes SVG — which can carry scripts, so it is
            // not an image in the safe sense. The size cap fits any phone
            // photo while stopping arbitrarily large blobs.
            'levelImage' => ['required', File::image()->max(10 * 1024)],
        ];
    }
}
