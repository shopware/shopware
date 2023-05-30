<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Pagelet;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class HeaderPageletLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    private EntityRepository $languageRepository;

    protected function setUp(): void
    {
        $this->languageRepository = $this->getContainer()->get('language.repository');
    }

    /**
     * @dataProvider sortingTestDataProvider
     */
    public function testLanguageSorting(array $languages, ?array $expectedOrder = null): void
    {
        $request = new Request();

        foreach ($languages as &$language) {
            $language['id'] = $this->createLanguage($language['name']);
        }
        unset($language);

        $context = $this->createSalesChannelContext($this->prepareSalesChannelOverride($languages));

        $pageLanguages = $this->getPageLoader()->load($request, $context)->getHeader()->getLanguages()->getElements();

        $i = 0;
        foreach ($pageLanguages as $pageLanguage) {
            static::assertSame($expectedOrder[$i], $pageLanguage->getName());
            ++$i;
        }
    }

    /**
     * Warning: Sorting is done after the position of the character inside the used collation.
     * Some characters like A and Ä share one position since Ä is being seen as A with decorations.
     * Adding a test case with e.g. Alang and Älang with an expected order will introduce flakynes.
     */
    public static function sortingTestDataProvider(): array
    {
        return [
            [
                'languages' => [
                    ['name' => 'Alang'],
                    ['name' => 'Dlang'],
                    ['name' => 'Xlang'],
                    ['name' => 'Blang'],
                ],
                'expected_order' => ['Alang', 'Blang', 'Dlang', 'Xlang'],
            ],
            [
                'languages' => [
                    ['name' => 'Русский'],
                    ['name' => 'हिन्दी'],
                    ['name' => 'Glang'],
                    ['name' => 'Ölang'],
                    ['name' => 'Xlang'],
                    ['name' => 'Elang'],
                    ['name' => 'Flang'],
                    ['name' => 'Plang'],
                    ['name' => 'Qlang'],
                    ['name' => 'Ylang'],
                    ['name' => 'Mlang'],
                    ['name' => 'Rlang'],
                    ['name' => 'Jlang'],
                    ['name' => '한국어'],
                    ['name' => 'Slang'],
                    ['name' => 'Ülang'],
                    ['name' => 'Älang'],
                    ['name' => 'Llang'],
                ],
                'expected_order' => [
                    'Älang',
                    'Elang',
                    'Flang',
                    'Glang',
                    'Jlang',
                    'Llang',
                    'Mlang',
                    'Ölang',
                    'Plang',
                    'Qlang',
                    'Rlang',
                    'Slang',
                    'Ülang',
                    'Xlang',
                    'Ylang',
                    'Русский',
                    'हिन्दी',
                    '한국어',
                ],
            ],
            [
                'languages' => [
                    ['name' => 'Alang'],
                    ['name' => 'Ablang'],
                    ['name' => 'Axlang'],
                    ['name' => 'Arlang'],
                    ['name' => 'Aolang'],
                    ['name' => 'Azlang'],
                    ['name' => 'Anlang'],
                    ['name' => 'Aqlang'],
                    ['name' => 'Aülang'],
                ],
                'expected_order' => ['Ablang', 'Alang', 'Anlang', 'Aolang', 'Aqlang', 'Arlang', 'Aülang', 'Axlang', 'Azlang'],
            ],
        ];
    }

    protected function getPageLoader(): GenericPageLoader
    {
        return $this->getContainer()->get(GenericPageLoader::class);
    }

    private function prepareSalesChannelOverride(array $languages): array
    {
        $languageIdArray = [];
        foreach ($languages as $language) {
            $languageIdArray[] = ['id' => $language['id']];
        }
        $domainArray = $this->getDomains($languages);

        return ['languages' => $languageIdArray, 'domains' => $domainArray, 'languageId' => $languages[0]['id']];
    }

    private function getDomains(array $languages): array
    {
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        $domains = [];

        foreach ($languages as $language) {
            $domains[] = ['url' => 'http://test.com/' . $language['id'], 'currencyId' => Defaults::CURRENCY, 'languageId' => $language['id'], 'snippetSetId' => $snippetSetId];
        }

        return $domains;
    }

    private function createLanguage($name): string
    {
        $localeId = Uuid::randomHex();
        $id = Uuid::randomHex();
        $this->languageRepository->upsert([
            [
                'id' => $id,
                'name' => $name,
                'locale' => [
                    'id' => $localeId,
                    'code' => $localeId,
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
                'translationCodeId' => $localeId,
            ],
        ], Context::createDefaultContext());

        return $id;
    }
}
