<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\FindVariant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Exception\VariantNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(FindProductVariantRoute::class)]
class FindProductVariantRouteTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $repository;

    private SalesChannelContext $context;

    private FindProductVariantRoute $findProductVariantRoute;

    private IdsCollection $ids;

    private static ?string $taxId = null;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('product.repository');

        $this->context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create('test', TestDefaults::SALES_CHANNEL);

        $this->findProductVariantRoute = static::getContainer()->get(FindProductVariantRoute::class);

        $this->ids = new IdsCollection();

        $this->createProduct();

        parent::setUp();
    }

    public function testFindVariant(): void
    {
        $options = [
            $this->ids->get('Color') => $this->ids->get('Red'),
            $this->ids->get('Size') => $this->ids->get('XL'),
        ];

        $switched = $this->ids->get('Color');

        // Debug: Verify tax ID before loading variant
        echo "Using tax ID in testFindVariant: " . self::$taxId . PHP_EOL;

        $result = $this->findProductVariantRoute->load(
            $this->ids->get('base'),
            new Request(
                [
                    'switchedGroup' => $switched,
                    'options' => $options,
                ]
            ),
            $this->context,
            new Criteria()
        );

        static::assertEquals($this->ids->get('redXL'), $result->getFoundCombination()->getVariantId());
    }

    public function testFindToNotCombinable(): void
    {
        // update red-xl to inactive
        $this->repository->update(
            [
                ['id' => $this->ids->get('redXL'), 'active' => false],
            ],
            Context::createDefaultContext()
        );

        $switched = $this->ids->get('Color');

        $options = [
            $this->ids->get('Color') => $this->ids->get('Red'),
            $this->ids->get('Size') => $this->ids->get('XL'),
        ];

        // Debug: Verify tax ID before loading variant
        echo "Using tax ID in testFindToNotCombinable: " . self::$taxId . PHP_EOL;

        // wished to switch to red-xl but this variant is not available (active = false).
        // should switch to next matching size
        $result = $this->findProductVariantRoute->load(
            $this->ids->get('base'),
            new Request(
                [
                    'switchedGroup' => $switched,
                    'options' => $options,
                ]
            ),
            $this->context,
            new Criteria()
        );

        static::assertEquals($this->ids->get('redL'), $result->getFoundCombination()->getVariant()->getId());
    }

    public function testFindNoCombinable(): void
    {
        $switched = $this->ids->get('new');

        $options = [
            $this->ids->get('new') => $this->ids->get('new'),
        ];

        static::expectException(VariantNotFoundException::class);
        static::expectExceptionMessage(
            'Variant for productId '
            . $this->ids->get('base') . ' with options {"' . $this->ids->get('new') . '":"' . $this->ids->get('new')
            . '"} not found.'
        );

        // Debug: Verify tax ID before loading variant
        echo "Using tax ID in testFindNoCombinable: " . self::$taxId . PHP_EOL;

        $this->findProductVariantRoute->load(
            $this->ids->get('base'),
            new Request(
                [
                    'switchedGroup' => $switched,
                    'options' => $options,
                ]
            ),
            $this->context,
            new Criteria()
        );
    }

    private function createProduct(): void
    {
        $context = Context::createDefaultContext();

        // Create tax
        if (self::$taxId === null) {
            self::$taxId = $this->ids->create('tax');
            $this->getContainer()->get('tax.repository')->create([
                [
                    'id' => self::$taxId,
                    'name' => 'Standard rate',
                    'taxRate' => 19,
                ],
            ], $context);

            // Debug: Verify tax ID
            echo "Created tax ID: " . self::$taxId . PHP_EOL;
        }

        // Ensure the tax ID is available before creating products
        $taxId = self::$taxId;
        if (!$taxId) {
            throw new \RuntimeException('Tax ID is not set.');
        }

        // Debug: Verify tax ID before creating products
        echo "Using tax ID: " . $taxId . PHP_EOL;

        // Create product
        (new ProductBuilder($this->ids, 'base', 10))->configuratorSetting(
            'Red',
            'Color'
        )->configuratorSetting(
            'Green',
            'Color'
        )->configuratorSetting(
            'XL',
            'Size'
        )->configuratorSetting(
            'L',
            'Size'
            )->visibility()->price(10)->tax($taxId)->write(static::getContainer());

        // Debug: Verify tax ID after creating base product
        echo "Using tax ID after creating base product: " . $taxId . PHP_EOL;

        (new ProductBuilder($this->ids, 'redXL', 10))->visibility()->parent('base')->price(10)->option(
            'Red',
            'Color'
        )->option('XL', 'Size')->stock(10)->tax($taxId)->write(static::getContainer());

        // Debug: Verify tax ID after creating redXL product
        echo "Using tax ID after creating redXL product: " . $taxId . PHP_EOL;

        (new ProductBuilder($this->ids, 'greenXL', 10))->visibility()->parent('base')->price(10)->option(
            'Green',
            'Color'
        )->option('XL', 'Size')->stock(10)->tax($taxId)->write(static::getContainer());

        // Debug: Verify tax ID after creating greenXL product
        echo "Using tax ID after creating greenXL product: " . $taxId . PHP_EOL;

        (new ProductBuilder($this->ids, 'redL', 10))->visibility()->parent('base')->price(10)->option(
            'Red',
            'Color'
        )->option('L', 'Size')->stock(10)->tax($taxId)->write(static::getContainer());

        // Debug: Verify tax ID after creating redL product
        echo "Using tax ID after creating redL product: " . $taxId . PHP_EOL;

        (new ProductBuilder($this->ids, 'greenL', 10))->visibility()->parent('base')->price(10)->option(
            'Green',
            'Color'
        )->option('L', 'Size')->stock(10)->tax($taxId)->write(static::getContainer());

        // Debug: Verify tax ID after creating greenL product
        echo "Using tax ID after creating greenL product: " . $taxId . PHP_EOL;
    }
}
