<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
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
            'registration_token' => ['required', 'string'],
            'fcm_token' => ['nullable', 'string'],
            'device_info' => ['required', 'array'],
            'device_info.serial_number' => ['required', 'string'],
            'device_info.android_version' => ['required', 'string'],
            'device_info.imei' => ['nullable', 'string'],
        ];
    }
}

