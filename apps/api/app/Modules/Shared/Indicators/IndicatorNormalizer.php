<?php

declare(strict_types=1);

namespace App\Modules\Shared\Indicators;

/** Detects the type and canonicalises the value, so equivalent spellings dedup to one indicator. */
final class IndicatorNormalizer
{
    /** Hex digests: MD5 (32), SHA-1 (40), SHA-256 (64). */
    private const array HASH_LENGTHS = [32, 40, 64];

    private const string DOMAIN_PATTERN = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{1,59})$/';

    public function normalize(string $raw, ?IndicatorType $type = null): Indicator
    {
        $candidate = rtrim(strtolower(trim($raw)), '.');

        if ($candidate === '') {
            throw new InvalidIndicator('Indicator must not be empty.');
        }

        $detected = $this->detect($candidate);

        if ($detected === null) {
            throw new InvalidIndicator('Indicator is not a valid IP address, domain or file hash.');
        }

        if ($type !== null && $type !== $detected->type) {
            throw new InvalidIndicator(sprintf('Indicator is not a valid %s.', $type->value));
        }

        return $detected;
    }

    private function detect(string $value): ?Indicator
    {
        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            $packed = inet_pton($value);

            // ::ffff:a.b.c.d is the same host as a.b.c.d.
            if ($packed !== false && strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
                $packed = substr($packed, 12);
            }

            return new Indicator(IndicatorType::Ip, $packed === false ? $value : (string) inet_ntop($packed));
        }

        if (in_array(strlen($value), self::HASH_LENGTHS, true) && ctype_xdigit($value)) {
            return new Indicator(IndicatorType::Hash, $value);
        }

        if (preg_match('/[^\x00-\x7F]/', $value) === 1) {
            $ascii = idn_to_ascii($value, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            $value = $ascii === false ? $value : $ascii;
        }

        if (preg_match(self::DOMAIN_PATTERN, $value) === 1) {
            return new Indicator(IndicatorType::Domain, $value);
        }

        return null;
    }
}
