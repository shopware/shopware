<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\DataTransfer\Language;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\SnippetException;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Language::class)]
class LanguageTest extends TestCase
{
    public function testLanguageThrowsExceptionIfIndexedWithInvalidLocales(): void
    {
        $this->expectExceptionObject(SnippetException::localeDoesNotExist('invalid_locale'));

        new Language('invalid-locale', 'Invalid Language');
    }

    public function testCreateLanguageWithValidLocale(): void
    {
        $language = new Language('en-GB', 'English');
        static::assertSame('en-GB', $language->locale);
        static::assertSame('English', $language->name);
    }

    public function testCreateLanguageWithAllowedPseudoLocale(): void
    {
        $language = new Language('ach-UG', 'Acholi (Pseudo Language)');
        static::assertSame('ach-UG', $language->locale);
        static::assertSame('Acholi (Pseudo Language)', $language->name);
    }
}
