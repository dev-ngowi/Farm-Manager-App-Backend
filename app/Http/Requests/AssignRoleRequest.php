<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            // Ensure the roles are correctly configured in your Spatie setup
            'role' => 'required|string|in:Farmer,Vet,Researcher,Admin'
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        Log::warning('❌ Role Assignment Failed - Validation Error', [
            'user_id' => auth()->id() ?? 'unknown',
            'input' => $this->all(),
            'errors' => $validator->errors()->all()
        ]);

        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'message' => 'Invalid role selected', // Specific error message for Flutter to catch
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
