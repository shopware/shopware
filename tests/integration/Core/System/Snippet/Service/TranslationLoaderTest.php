<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Snippet\Service;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationLoaderTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use GuzzleTestClientBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<LocaleCollection>
     */
    private EntityRepository $localeRepository;

    /**
     * @var EntityRepository<SnippetSetCollection>
     */
    private EntityRepository $snippetSetRepository;

    protected function setUp(): void
    {
        $this->localeRepository = static::getContainer()->get('locale.repository');
        $this->snippetSetRepository = static::getContainer()->get('snippet_set.repository');
    }

    public function testSnippetSetOnlyCreatedOnce(): void
    {
        $context = Context::createDefaultContext();
        $locale = 'es-ES';

        // Ensure there's an existing snippet set for the locale but no BASE snippet set
        $this->createLocaleIfNotExists($context, $locale, 'Español', 'España');
        $this->createSnippetSetIfNotExists($context, $locale, 'LanguagePack');
        $this->deleteSnippetSetIfExists($context, $locale, 'BASE');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', $locale));
        $criteria->addFilter(new EqualsFilter('name', "BASE $locale"));

        $countBeforeFirstLoad = $this
            ->snippetSetRepository
            ->searchIds($criteria, $context)
            ->getTotal();
        static::assertSame(0, $countBeforeFirstLoad);

        $this->appendNewResponse(new Response(200, [], '{}'));
        $this->appendNewResponse(new Response(200, [], '{}'));
        $this->appendNewResponse(new Response(200, [], '{}'));

        $loader = static::getContainer()->get(TranslationLoader::class);
        static::assertInstanceOf(TranslationLoader::class, $loader);

        $loader->load($locale, $context);

        $countAfterFirstLoad = $this
            ->snippetSetRepository
            ->searchIds($criteria, $context)
            ->getTotal();

        static::assertSame(1, $countAfterFirstLoad);

        $this->appendNewResponse(new Response(200, [], '{}'));
        $this->appendNewResponse(new Response(200, [], '{}'));
        $this->appendNewResponse(new Response(200, [], '{}'));

        $loader->load($locale, $context);

        $countAfterSecondLoad = $this
            ->snippetSetRepository
            ->searchIds($criteria, $context)
            ->getTotal();

        static::assertSame(1, $countAfterSecondLoad);
    }

    private function createLocaleIfNotExists(
        Context $context,
        string $locale,
        string $name,
        string $territory
    ): void {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('code', $locale));
        $localeId = $this
            ->localeRepository
            ->searchIds($criteria, $context)
            ->firstId();

        if ($localeId !== null) {
            return;
        }

        $this
            ->localeRepository
            ->create([
                ['code' => $locale, 'name' => $name, 'territory' => $territory],
            ], $context);
    }

    private function createSnippetSetIfNotExists(
        Context $context,
        string $locale,
        ?string $namePrefix
    ): void {
        $snippetSetName = $namePrefix === null ? $locale : "$namePrefix $locale";
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $snippetSetName));

        $snippetSetId = $this
            ->snippetSetRepository
            ->searchIds($criteria, $context)
            ->firstId();

        if ($snippetSetId !== null) {
            return;
        }

        $this
            ->snippetSetRepository
            ->create([
                ['name' => $snippetSetName, 'baseFile' => "messages.$locale", 'iso' => $locale],
            ], $context);
    }

    private function deleteSnippetSetIfExists(
        Context $context,
        string $locale,
        ?string $namePrefix
    ): void {
        $snippetSetName = $namePrefix === null ? $locale : "$namePrefix $locale";
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $snippetSetName));

        $snippetSetId = $this
            ->snippetSetRepository
            ->searchIds($criteria, $context)
            ->firstId();

        if ($snippetSetId === null) {
            return;
        }
        $this->snippetSetRepository->delete([['id' => $snippetSetId]], $context);
    }
}
