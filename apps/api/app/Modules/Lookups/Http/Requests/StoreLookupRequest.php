<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Http\Requests;

use App\Modules\Shared\Indicators\IndicatorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLookupRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'indicator' => ['required', 'string', 'max:255'],
            'indicator_type' => ['nullable', Rule::enum(IndicatorType::class)],
            'provider' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function indicator(): string
    {
        return $this->string('indicator')->toString();
    }

    public function indicatorType(): ?IndicatorType
    {
        return $this->enum('indicator_type', IndicatorType::class);
    }

    public function provider(): ?string
    {
        return $this->filled('provider') ? $this->string('provider')->toString() : null;
    }
}
