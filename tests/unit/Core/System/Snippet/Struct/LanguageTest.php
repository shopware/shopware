<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\Language;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Language::class)]
class LanguageTest extends TestCase
{
    public function testLanguageThrowsExceptionIfIndexedWithInvalidLocales(): void
    {
        $this->expectException(SnippetException::class);
        $this->expectExceptionMessage('The configured locale "invalid_locale" does not exist.');

        new Language('invalid-locale', 'Invalid Language');
    }

    public function testCreateLanguageWithValidLocale(): void
    {
        $language = new Language('en-GB', 'English');
        static::assertSame('en-GB', $language->locale);
        static::assertSame('English', $language->name);
    }
}
