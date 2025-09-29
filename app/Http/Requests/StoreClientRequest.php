<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json($validator->errors(), 422)
        );
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "full_name" => "required|string|max:255",
            "phone_number" => "required|string|max:20",
            "address" => "nullable|string",
            "identity_document_type" => "required|string|in:passport,national_id,driver_license",
            "identity_document_number" => "required|string|max:100",
            "identity_document_proof" => "nullable|file|mimes:jpg,jpeg,png,pdf|max:2048",
            "total_price" => "required|numeric|min:0",
            "down_payment" => "required|numeric|min:0",
            "installment_amount" => "required|numeric|min:0",
        ];
    }
}


