<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\ProductAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('services-settings')]
class ProductStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof ProductAware || isset($stored[ProductAware::PRODUCT_ID])) {
            return $stored;
        }

        $stored[ProductAware::PRODUCT_ID] = $event->getProductId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(ProductAware::PRODUCT_ID)) {
            return;
        }

        $storable->lazy(
            ProductAware::PRODUCT,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?ProductEntity
    {
        $id = $storableFlow->getStore(ProductAware::PRODUCT_ID);
        if ($id === null) {
            return null;
        }

        $criteria = new Criteria([$id]);

        return $this->loadProduct($criteria, $storableFlow->getContext(), $id);
    }

    private function loadProduct(Criteria $criteria, Context $context, string $id): ?ProductEntity
    {
        $context->setConsiderInheritance(true);

        $event = new BeforeLoadStorableFlowDataEvent(
            ProductDefinition::ENTITY_NAME,
            $criteria,
            $context,
        );

        $this->dispatcher->dispatch($event, $event->getName());

        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search($criteria, $context)->get($id);

        return $product;
    }
}
