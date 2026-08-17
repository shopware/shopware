<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\VatIdPattern;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(VatIdPattern::class)]
class VatIdPatternTest extends TestCase
{
    #[DataProvider('matchesProvider')]
    public function testMatches(string $pattern, string $vatId, bool $expected): void
    {
        $vatIdPattern = new VatIdPattern('NL', $pattern);

        static::assertSame($expected, $vatIdPattern->matches($vatId));
    }

    public function testIsValidAcceptsACompilablePattern(): void
    {
        $pattern = new VatIdPattern('NL', 'NL\d{9}B\d{2}');

        static::assertTrue($pattern->isValid());
    }

    public function testIsValidRejectsAPatternThatDoesNotCompile(): void
    {
        $pattern = new VatIdPattern('NL', 'NL[0-9');

        static::assertFalse($pattern->isValid());
    }

    public function testIsValidRejectsAPatternThatBreaksOutOfTheDelimiters(): void
    {
        $pattern = new VatIdPattern('NL', 'NL/i');

        static::assertFalse($pattern->isValid());
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function matchesProvider(): iterable
    {
        yield 'plain pattern matches its own country' => ['NL\d{9}B\d{2}', 'NL123456789B01', true];

        yield 'plain pattern rejects a foreign vat id' => ['NL\d{9}B\d{2}', 'BE0123456789', false];

        yield 'the pattern is anchored, so a trailing suffix is rejected' => ['NL\d{9}B\d{2}', 'NL123456789B01X', false];

        yield 'the pattern is anchored, so a leading prefix is rejected' => ['NL\d{9}B\d{2}', 'XNL123456789B01', false];

        // The shipped Spanish pattern embeds its own anchors and only works with the wrapping anchors.
        yield 'shipped Spanish pattern accepts all three of its alternatives' => ['ES[A-Z]\d{7}[A-Z]$|^ES[A-Z][0-9]{7}[0-9A-Z]$|^ES[0-9]{8}[A-Z]', 'ESX1234567R', true];

        yield 'shipped Spanish pattern stays anchored on its last alternative' => ['ES[A-Z]\d{7}[A-Z]$|^ES[A-Z][0-9]{7}[0-9A-Z]$|^ES[0-9]{8}[A-Z]', 'ES12345678XX', false];

        yield 'shipped Irish pattern accepts the pre-2013 form' => ['IE(\d{7}[A-Z]{1,2}|(\d{1}[A-Z]{1}\d{5}[A-Z]{1}))', 'IE1B12345D', true];

        yield 'shipped Irish pattern accepts the post-2013 form' => ['IE(\d{7}[A-Z]{1,2}|(\d{1}[A-Z]{1}\d{5}[A-Z]{1}))', 'IE1234567FA', true];

        yield 'shipped Greek pattern accepts both the EL and GR prefix' => ['(EL|GR)\d{9}', 'EL123456789', true];

        yield 'shipped Romanian pattern rejects a leading zero via its lookahead' => ['RO(?!0)\d{1,10}', 'RO0123456789', false];
    }
}
