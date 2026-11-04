<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TranslatedString::class)]
class TranslatedStringTest extends TestCase
{
    /**
     * @param array<string, string> $translations
     */
    #[DataProvider('forLocaleProvider')]
    public function testForLocale(array $translations, string $locale, ?string $expected): void
    {
        static::assertSame($expected, (new TranslatedString($translations))->forLocale($locale));
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string|null}>
     */
    public static function forLocaleProvider(): iterable
    {
        yield 'requested locale' => [['en-GB' => 'Orders', 'de-DE' => 'Bestellungen'], 'de-DE', 'Bestellungen'];
        yield 'falls back to en-GB' => [['de-DE' => 'Bestellungen', 'en-GB' => 'Orders'], 'fr-FR', 'Orders'];
        yield 'falls back to the first translation without en-GB' => [['de-DE' => 'Bestellungen', 'nl-NL' => 'Bestellingen'], 'fr-FR', 'Bestellungen'];
        yield 'empty value of the requested locale is kept' => [['de-DE' => '', 'en-GB' => 'Orders'], 'de-DE', ''];
        yield 'no translations' => [[], 'en-GB', null];
    }

    public function testAllReturnsTheDeclaredTranslations(): void
    {
        $translations = ['de-DE' => 'Bestellungen'];

        static::assertSame($translations, (new TranslatedString($translations))->all());
    }
}
