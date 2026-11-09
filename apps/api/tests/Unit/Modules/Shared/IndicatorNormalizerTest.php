<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Shared;

use App\Modules\Shared\Indicators\IndicatorNormalizer;
use App\Modules\Shared\Indicators\IndicatorType;
use App\Modules\Shared\Indicators\InvalidIndicator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IndicatorNormalizerTest extends TestCase
{
    /** @return iterable<string, array{string, IndicatorType, string}> */
    public static function detectable(): iterable
    {
        yield 'ipv4' => ['8.8.8.8', IndicatorType::Ip, '8.8.8.8'];
        yield 'ipv4 padded' => ['  10.0.0.1 ', IndicatorType::Ip, '10.0.0.1'];
        yield 'ipv6 canonicalised' => ['2001:0DB8:0000:0000:0000:0000:0000:0001', IndicatorType::Ip, '2001:db8::1'];
        yield 'ipv4-mapped ipv6 folds to ipv4' => ['::ffff:8.8.8.8', IndicatorType::Ip, '8.8.8.8'];
        yield 'unicode domain to punycode' => ['Bücher.de', IndicatorType::Domain, 'xn--bcher-kva.de'];
        yield 'domain lowercased' => ['Example.COM', IndicatorType::Domain, 'example.com'];
        yield 'domain trailing dot' => ['evil.example.org.', IndicatorType::Domain, 'evil.example.org'];
        yield 'md5' => [str_repeat('A', 32), IndicatorType::Hash, str_repeat('a', 32)];
        yield 'sha1' => [str_repeat('b', 40), IndicatorType::Hash, str_repeat('b', 40)];
        yield 'sha256' => [str_repeat('c', 64), IndicatorType::Hash, str_repeat('c', 64)];
    }

    #[DataProvider('detectable')]
    public function test_detects_and_normalises(string $raw, IndicatorType $type, string $value): void
    {
        $indicator = (new IndicatorNormalizer)->normalize($raw);

        self::assertSame($type, $indicator->type);
        self::assertSame($value, $indicator->value);
    }

    public function test_explicit_type_must_match_value(): void
    {
        $this->expectException(InvalidIndicator::class);

        (new IndicatorNormalizer)->normalize('8.8.8.8', IndicatorType::Domain);
    }

    public function test_explicit_type_is_accepted_when_valid(): void
    {
        $indicator = (new IndicatorNormalizer)->normalize(str_repeat('f', 64), IndicatorType::Hash);

        self::assertSame(IndicatorType::Hash, $indicator->type);
    }

    /** @return iterable<string, array{string}> */
    public static function invalid(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'not hex hash length' => [str_repeat('z', 32)];
        yield 'single label' => ['localhost'];
        yield 'garbage' => ['not an indicator!'];
        yield 'too long' => [str_repeat('a', 250).'.com'];
    }

    #[DataProvider('invalid')]
    public function test_rejects_invalid_input(string $raw): void
    {
        $this->expectException(InvalidIndicator::class);

        (new IndicatorNormalizer)->normalize($raw);
    }
}
