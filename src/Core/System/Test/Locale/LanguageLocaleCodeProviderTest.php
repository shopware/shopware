<?php declare(strict_types=1);

namespace Locale;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

class LanguageLocaleCodeProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private LanguageLocaleCodeProvider $languageLocaleProvider;

    public function setUp(): void
    {
        $this->languageLocaleProvider = $this->getContainer()->get(LanguageLocaleCodeProvider::class);
    }

    public function testGetLocaleForLanguageId(): void
    {
        static::assertEquals('en-GB', $this->languageLocaleProvider->getLocaleForLanguageId(Defaults::LANGUAGE_SYSTEM));
        static::assertEquals('de-DE', $this->languageLocaleProvider->getLocaleForLanguageId($this->getDeDeLanguageId()));
    }

    public function testGetLocaleForLanguageIdThrowsForNotExistingLanguage(): void
    {
        static::expectException(LanguageNotFoundException::class);
        $this->languageLocaleProvider->getLocaleForLanguageId(Uuid::randomHex());
    }

    public function testGetLocalesForLanguageIds(): void
    {
        $deDeLanguage = $this->getDeDeLanguageId();

        static::assertEquals([
            Defaults::LANGUAGE_SYSTEM => 'en-GB',
            $deDeLanguage => 'de-DE',
        ], $this->languageLocaleProvider->getLocalesForLanguageIds([Defaults::LANGUAGE_SYSTEM, $deDeLanguage]));
    }
}
