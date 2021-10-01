<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;

class EntityLoadedEventFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepositoryInterface $productRepository;

    private IdsCollection $ids;

    private EntityLoadedEventFactory $entityLoadedEventFactory;

    public function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->entityLoadedEventFactory = $this->getContainer()->get(EntityLoadedEventFactory::class);
        $this->ids = new IdsCollection();
    }

    public function testCreate(): void
    {
        $builder = (new ProductBuilder($this->ids, 'p1'))
            ->price(10)
            ->category('c1')
            ->manufacturer('m1')
            ->prices('r1', 5);

        $this->productRepository->create([$builder->build()], $this->ids->getContext());

        $criteria = new Criteria();
        $criteria->addAssociations([
            'manufacturer',
            'prices',
            'categories',
        ]);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $this->ids->getContext())->first();
        $product->addExtension('test', new LanguageCollection([
            (new LanguageEntity())->assign(['id' => $this->ids->create('l1'), '_entityName' => 'language']),
        ]));
        $events = $this->entityLoadedEventFactory->create([$product], $this->ids->getContext());

        $createdEvents = $events->getEvents()->map(function (EntityLoadedEvent $event): string {
            return $event->getName();
        });
        sort($createdEvents);

        static::assertEquals([
            'category.loaded',
            'language.loaded',
            'product.loaded',
            'product_manufacturer.loaded',
            'product_price.loaded',
            'tax.loaded',
        ], $createdEvents);
    }
}
