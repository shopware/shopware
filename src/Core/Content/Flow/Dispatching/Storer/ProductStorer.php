<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\ProductProvider;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\ProductAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class ProductStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ProductProvider $productProvider,
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

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria = $this->productProvider->getCriteria($id, $storableFlow->getContext());

            $event = new BeforeLoadStorableFlowDataEvent(
                ProductDefinition::ENTITY_NAME,
                $criteria,
                $storableFlow->getContext(),
            );

            $this->dispatcher->dispatch($event, $event->getName());

            $product = $this->productRepository->search($criteria, $storableFlow->getContext())->getEntities()->get($id);

            if ($product) {
                return $product;
            }

            return null;
        }

        return $this->productProvider->getData($id, $storableFlow->getContext());
    }
}
