<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Language;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\System\Language\LanguageLoader;
use Shopware\Core\System\Language\LanguageLoaderInterface;

/**
 * @internal
 */
class LanguageLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private LanguageLoaderInterface $languageLoader;

    private TestDataCollection $ids;

    public function setUp(): void
    {
        $this->languageLoader = $this->getContainer()->get(LanguageLoader::class);

        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->createData();
    }

    public function testLocaleCodeResolver(): void
    {
        $languages = $this->languageLoader->loadLanguages();

        static::assertEquals('en-GB', $languages[Defaults::LANGUAGE_SYSTEM]['code']);
        static::assertEquals('de-DE', $languages[$this->getDeDeLanguageId()]['code']);

        static::assertEquals('language-locale', $languages[$this->ids->get('language-parent')]['code']);
        static::assertEquals('language-locale', $languages[$this->ids->get('language-child')]['code']);
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
        ], $this->ids->context);

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
            ->create($data, $this->ids->context);
    }
}
