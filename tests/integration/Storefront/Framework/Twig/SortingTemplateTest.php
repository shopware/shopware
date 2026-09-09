<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
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
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return $twig->render('@Storefront/storefront/component/sorting.html.twig', [
            'current' => '',
            'sortings' => $sortings,
        ]);
    }

    private function createSorting(string $key, string $label): ProductSortingEntity
    {
        $sorting = new ProductSortingEntity();
        $sorting->setUniqueIdentifier(Uuid::randomHex());
        $sorting->setKey($key);
        $sorting->setTranslated(['label' => $label]);

        return $sorting;
    }
}
