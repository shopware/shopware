<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Locale\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\Util\LocaleHelper;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LocaleHelper::class)]
class LocaleHelperTest extends TestCase
{
    #[DataProvider('localeProvider')]
    public function testItIsLocale(string $locale, bool $expected): void
    {
        $isLocale = LocaleHelper::isLocale($locale);

        static::assertSame($expected, $isLocale);
    }

    public static function localeProvider(): \Generator
    {
        yield 'de_DE' => ['de_DE', true];
        yield 'en_US' => ['en_US', true];
        yield 'fr_FR' => ['fr_FR', true];
        yield 'en-GB' => ['en-GB', true];
        yield 'aa-DJ' => ['aa-DJ', true];
        yield 'aa-ER' => ['aa-ER', true];
        yield 'fur-IT' => ['fur-IT', true];
        yield 'gsw-CH' => ['gsw-CH', true];
        yield 'haw-US' => ['haw-US', true];
        yield 'nds-DE' => ['nds-DE', true];
        yield 'kok-IN' => ['kok-IN', true];
        yield 'de-US' => ['de-US', true];
        yield 'fr-JP' => ['fr-JP', true];
        yield 'en-DE' => ['en-DE', true];
        yield 'sr-Latn-RS' => ['sr-Latn-RS', true];
        yield 'zh-Hant-TW' => ['zh-Hant-TW', true];
        yield 'sr-CS' => ['sr-CS', true];
        yield 'sh-CS' => ['sh-CS', true];
        yield 'en-us' => ['en-us', true];
        yield 'EN_us' => ['EN_us', true];
        yield 'de-de' => ['de-de', true];
        yield 'de' => ['de', false];
        yield 'en' => ['en', false];
        yield 'xx-DE' => ['xx-DE', false];
        yield 'foo-BAR' => ['foo-BAR', false];
        yield 'de-XX' => ['de-XX', false];
        yield 'en-123' => ['en-123', false];
        yield 'de--DE' => ['de--DE', false];
        yield '-DE' => ['-DE', false];
        yield 'de-' => ['de-', false];
        yield 'nl-NL-2' => ['nl-NL-2', true];
        yield 'de-DE-abc' => ['de-DE-abc', true];
        yield 'de-DE-1996' => ['de-DE-1996', true];
        yield ' ' => [' ', false];
        yield '___' => ['___', false];
        yield '123' => ['123', false];
        yield 'es-419' => ['es-419', false];
        yield 'en-US-x-twain' => ['en-US-x-twain', true];
        yield 'de-DE-u-co-phonebk' => ['de-DE-u-co-phonebk', true];
    }
}
