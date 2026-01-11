<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Allow all users to make bookings
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where('is_active', true),
            ],
            'date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'start_time' => [
                'required',
                'date',
                'date_format:Y-m-d H:i:s',
            ],
            'end_time' => [
                'required',
                'date',
                'date_format:Y-m-d H:i:s',
                'after:start_time',
            ],
            'participants' => [
                'required',
                'array',
                'size:1', // Exactly one participant
            ],
            'participants.*.first_name' => [
                'required',
                'string',
                'min:1',
                'max:255',
                'regex:/^[\p{L}\s\-\']+$/u', // Allow letters, spaces, hyphens, apostrophes
            ],
            'participants.*.last_name' => [
                'required',
                'string',
                'min:1',
                'max:255',
                'regex:/^[\p{L}\s\-\']+$/u',
            ],
            'participants.*.email' => [
                'required',
                'email:rfc,dns', // Strict email validation
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_id.required' => 'Service ID is required.',
            'service_id.exists' => 'The selected service does not exist or is not active.',
            'date.required' => 'Date is required.',
            'date.date_format' => 'Date must be in Y-m-d format (e.g., 2026-01-15).',
            'date.after_or_equal' => 'Date cannot be in the past.',
            'start_time.required' => 'Start time is required.',
            'start_time.date_format' => 'Start time must be in Y-m-d H:i:s format (e.g., 2026-01-15 10:00:00).',
            'end_time.required' => 'End time is required.',
            'end_time.date_format' => 'End time must be in Y-m-d H:i:s format (e.g., 2026-01-15 10:30:00).',
            'end_time.after' => 'End time must be after start time.',
            'participants.required' => 'At least one participant is required.',
            'participants.size' => 'Exactly one participant is required per appointment.',
            'participants.*.first_name.required' => 'First name is required for all participants.',
            'participants.*.first_name.regex' => 'First name can only contain letters, spaces, hyphens, and apostrophes.',
            'participants.*.last_name.required' => 'Last name is required for all participants.',
            'participants.*.last_name.regex' => 'Last name can only contain letters, spaces, hyphens, and apostrophes.',
            'participants.*.email.required' => 'Email is required for all participants.',
            'participants.*.email.email' => 'Please provide a valid email address.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that start_time and end_time match the date
            if ($this->has('date') && $this->has('start_time') && $this->has('end_time')) {
                $date = $this->input('date');
                $startTime = $this->input('start_time');
                $endTime = $this->input('end_time');

                if ($startTime && !str_starts_with($startTime, $date)) {
                    $validator->errors()->add(
                        'start_time',
                        'Start time date must match the selected date.'
                    );
                }

                if ($endTime && !str_starts_with($endTime, $date)) {
                    $validator->errors()->add(
                        'end_time',
                        'End time date must match the selected date.'
                    );
                }
            }
        });
    }
}
