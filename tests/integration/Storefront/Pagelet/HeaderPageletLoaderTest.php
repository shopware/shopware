<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Pagelet;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoader;
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
     * @param list<array{name: string}> $languages
     * @param list<string> $expectedOrder
     */
    #[DataProvider('sortingTestDataProvider')]
    public function testLanguageSorting(array $languages, array $expectedOrder): void
    {
        $createdLanguages = [];
        foreach ($languages as $language) {
            $createdLanguages[] = [
                'name' => $language['name'],
                'id' => $this->createLanguage($language['name']),
            ];
        }

        $context = $this->createSalesChannelContext($this->prepareSalesChannelOverride($createdLanguages));

        $pageLanguages = $this->getPageLoader()->load(new Request(), $context)->getLanguages()->getElements();

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
     *
     * @return array<array{languages: list<array{name: string}>, expectedOrder: list<string>}>
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
                'expectedOrder' => ['Alang', 'Blang', 'Dlang', 'Xlang'],
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
                'expectedOrder' => [
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
                'expectedOrder' => ['Ablang', 'Alang', 'Anlang', 'Aolang', 'Aqlang', 'Arlang', 'Aülang', 'Axlang', 'Azlang'],
            ],
        ];
    }

    protected function getPageLoader(): HeaderPageletLoader
    {
        return $this->getContainer()->get(HeaderPageletLoader::class);
    }

    /**
     * @param list<array{name: string, id: string}> $languages
     *
     * @return array{languages: list<array{id: string}>, domains: list<array{url: string, currencyId: string, languageId: string, snippetSetId: string|null}>, languageId: string}
     */
    private function prepareSalesChannelOverride(array $languages): array
    {
        $languageIdArray = [];
        foreach ($languages as $language) {
            $languageIdArray[] = ['id' => $language['id']];
        }
        $domainArray = $this->getDomains($languages);

        return ['languages' => $languageIdArray, 'domains' => $domainArray, 'languageId' => $languages[0]['id']];
    }

    /**
     * @param list<array{name: string, id: string}> $languages
     *
     * @return list<array{url: string, currencyId: string, languageId: string, snippetSetId: string|null}>
     */
    private function getDomains(array $languages): array
    {
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        $domains = [];

        foreach ($languages as $language) {
            $domains[] = ['url' => 'http://test.com/' . $language['id'], 'currencyId' => Defaults::CURRENCY, 'languageId' => $language['id'], 'snippetSetId' => $snippetSetId];
        }

        return $domains;
    }

    private function createLanguage(string $name): string
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
