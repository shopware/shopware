<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Locale;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * @internal
 */
class LanguageLocaleCodeProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private LanguageLocaleCodeProvider $languageLocaleProvider;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->languageLocaleProvider = $this->getContainer()->get(LanguageLocaleCodeProvider::class);

        $this->ids = new TestDataCollection();

        $this->createData();
    }

    public function testGetLocaleForLanguageId(): void
    {
        static::assertEquals('en-GB', $this->languageLocaleProvider->getLocaleForLanguageId(Defaults::LANGUAGE_SYSTEM));
        static::assertEquals('de-DE', $this->languageLocaleProvider->getLocaleForLanguageId($this->getDeDeLanguageId()));
        static::assertEquals('language-locale', $this->languageLocaleProvider->getLocaleForLanguageId($this->ids->get('language-child')));
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
            $this->ids->get('language-parent') => 'language-locale',
            $this->ids->get('language-child') => 'language-locale',
        ], $this->languageLocaleProvider->getLocalesForLanguageIds([
            Defaults::LANGUAGE_SYSTEM,
            $deDeLanguage,
            $this->ids->get('language-parent'),
            $this->ids->get('language-child'),
        ]));
    }

    private function createData(): void
    {
        $this->getContainer()->get('locale.repository')->create([
            [
                'id' => $this->ids->get('language-locale'),
                'code' => 'language-locale',
                'name' => 'language-locale',
                'territory' => 'language-locale',
            ],
        ], Context::createDefaultContext());

        $data = [
            [
                'id' => $this->ids->create('language-parent'),
                'name' => 'parent',
                'localeId' => $this->ids->get('language-locale'),
                'translationCodeId' => $this->ids->get('language-locale'),
            ],
            [
                'id' => $this->ids->create('language-child'),
                'name' => 'child',
                'parentId' => $this->ids->create('language-parent'),
                'localeId' => $this->ids->get('language-locale'),
                'translationCodeId' => null,
            ],
        ];

        $this->getContainer()->get('language.repository')
            ->create($data, Context::createDefaultContext());
    }
}
