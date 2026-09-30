<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class SortingTemplateTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * The `score` sorting is locked, but its translations are editable in the
     * administration, so its label must not be replaced by a snippet.
     */
    public function testScoreSortingKeepsItsTranslatedLabel(): void
    {
        $output = $this->renderSortings(new ProductSortingCollection([
            $this->createSorting(key: 'score', label: 'Label configured in the administration'),
        ]));

        static::assertStringContainsString('<option value="score">Label configured in the administration</option>', $output);
        static::assertStringNotContainsString('Top results', $output);
    }

    public function testConfigurableSortingKeepsItsTranslatedLabel(): void
    {
        $output = $this->renderSortings(new ProductSortingCollection([
            $this->createSorting(key: 'name-asc', label: 'Name A-Z'),
        ]));

        static::assertStringContainsString('<option value="name-asc">Name A-Z</option>', $output);
    }

    private function renderSortings(ProductSortingCollection $sortings): string
    {
        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        return $this->renderInStorefrontRequest('@Storefront/storefront/component/sorting.html.twig', [
            'current' => '',
            'sortings' => $sortings,
        ], $context);
    }

    private function createSorting(string $key, string $label): ProductSortingEntity
    {
        $sorting = new ProductSortingEntity();
        $sorting->setUniqueIdentifier(Uuid::randomHex());
        $sorting->setKey($key);
        $sorting->setTranslated(['label' => $label]);

        return $sorting;
    }

    /**
     * Renders inside a Storefront request, so `TemplateDataExtension` resolves real globals instead of caching
     * them empty in the shared environment, and resets them afterwards so later tests resolve their own.
     *
     * @param array<string, mixed> $parameters
     */
    private function renderInStorefrontRequest(string $template, array $parameters, SalesChannelContext $context): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);
        $requestStack = static::getContainer()->get(RequestStack::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $requestStack->push($request);

        try {
            return $twig->render($template, $parameters);
        } finally {
            $requestStack->pop();
            $twig->resetGlobals();
        }
    }
}
