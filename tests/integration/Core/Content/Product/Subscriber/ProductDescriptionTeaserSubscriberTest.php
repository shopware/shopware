<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ProductDescriptionTeaserSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testTeaserIsDerivedOnWrite(): void
    {
        $repo = $this->productRepository();
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        $repo->create([$this->productPayload($id, 'product-' . $id)], $context);

        $product = $repo->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertInstanceOf(ProductEntity::class, $product);

        $teaser = $product->getTranslation('descriptionTeaser');
        static::assertIsString($teaser);
        static::assertStringStartsWith('Lorem ipsum', $teaser);
        static::assertStringNotContainsString('<', $teaser, 'teaser must be HTML-stripped');
    }

    public function testCloningProductWithDerivedTeaserSucceeds(): void
    {
        $repo = $this->productRepository();
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        $repo->create([$this->productPayload($id, 'product-' . $id)], $context);

        // The write-protected `descriptionTeaser` is populated by the subscriber; cloning must not
        // fail on it and the clone must derive its own teaser from the copied description.
        $newId = Uuid::randomHex();
        $repo->clone($id, $context, $newId, new CloneBehavior(['productNumber' => 'clone-' . $newId]));

        $clone = $repo->search(new Criteria([$newId]), $context)->getEntities()->first();
        static::assertInstanceOf(ProductEntity::class, $clone);
        static::assertStringStartsWith('Lorem ipsum', (string) $clone->getTranslation('descriptionTeaser'));
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(string $id, string $productNumber): array
    {
        return [
            'id' => $id,
            'productNumber' => $productNumber,
            'name' => 'Teaser product',
            'description' => '<p style="color:red">' . str_repeat('Lorem ipsum dolor sit amet. ', 30) . '</p>',
            'stock' => 1,
            'tax' => ['name' => 'teaser-tax', 'taxRate' => 19],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
        ];
    }

    /**
     * @return EntityRepository<ProductCollection>
     */
    private function productRepository(): EntityRepository
    {
        $repo = static::getContainer()->get('product.repository');
        \assert($repo instanceof EntityRepository);

        return $repo;
    }
}
