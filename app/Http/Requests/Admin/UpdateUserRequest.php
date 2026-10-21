<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Hak akses dicek middleware can:... (UserPolicy).
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'role' => [
                'required',
                Rule::in(array_column(UserRole::cases(), 'value')),
                // Cegah Admin mengunci dirinya sendiri (mis. turun jadi Staf lalu kehilangan akses).
                function (string $attribute, mixed $value, Closure $fail) use ($target) {
                    if ($this->user()->is($target) && $value !== $target->role->value) {
                        $fail('Anda tidak bisa mengubah role akun Anda sendiri.');
                    }
                },
            ],
            // Opsional: kosongkan jika password tidak diganti.
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return UserFormMessages::all();
    }
}
