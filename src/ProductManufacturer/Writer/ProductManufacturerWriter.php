<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Writer;

use Shopware\Api\Write\FieldAware\DefaultExtender;
use Shopware\Api\Write\FieldAware\FieldExtenderCollection;
use Shopware\Api\Write\FieldException\WriteStackException;
use Shopware\Api\Write\ResourceWriterInterface;
use Shopware\Api\Write\WriteContext;
use Shopware\Api\Write\WriterInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEventDispatcherInterface;
use Shopware\ProductManufacturer\Event\ProductManufacturerWriteExtenderEvent;
use Shopware\ProductManufacturer\Event\ProductManufacturerWrittenEvent;
use Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class ProductManufacturerWriter implements WriterInterface
{
    /**
     * @var DefaultExtender
     */
    private $extender;

    /**
     * @var NestedEventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ResourceWriterInterface
     */
    private $writer;

    public function __construct(DefaultExtender $extender, NestedEventDispatcherInterface $eventDispatcher, ResourceWriterInterface $writer)
    {
        $this->extender = $extender;
        $this->eventDispatcher = $eventDispatcher;
        $this->writer = $writer;
    }

    public function update(array $data, TranslationContext $context): ProductManufacturerWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $updated = $errors = [];

        foreach ($data as $productManufacturer) {
            try {
                $updated[] = $this->writer->update(
                    ProductManufacturerWriteResource::class,
                    $productManufacturer,
                    $writeContext,
                    $extender
                );
            } catch (WriteStackException $exception) {
                $errors[] = $exception->toArray();
            }
        }

        $affected = count($updated);
        if ($affected === 1) {
            $updated = array_shift($updated);
        } elseif ($affected > 1) {
            $updated = array_merge_recursive(...$updated);
        }

        return ProductManufacturerWriteResource::createWrittenEvent($updated, $context, $data, $errors);
    }

    public function upsert(array $data, TranslationContext $context): ProductManufacturerWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $created = $errors = [];

        foreach ($data as $productManufacturer) {
            try {
                $created[] = $this->writer->upsert(
                    ProductManufacturerWriteResource::class,
                    $productManufacturer,
                    $writeContext,
                    $extender
                );
            } catch (WriteStackException $exception) {
                $errors[] = $exception->toArray();
            }
        }

        $affected = count($created);
        if ($affected === 1) {
            $created = array_shift($created);
        } elseif ($affected > 1) {
            $created = array_merge_recursive(...$created);
        }

        return ProductManufacturerWriteResource::createWrittenEvent($created, $context, $data, $errors);
    }

    public function create(array $data, TranslationContext $context): ProductManufacturerWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $created = $errors = [];

        foreach ($data as $productManufacturer) {
            try {
                $created[] = $this->writer->insert(
                    ProductManufacturerWriteResource::class,
                    $productManufacturer,
                    $writeContext,
                    $extender
                );
            } catch (WriteStackException $exception) {
                $errors[] = $exception->toArray();
            }
        }

        $affected = count($created);
        if ($affected === 1) {
            $created = array_shift($created);
        } elseif ($affected > 1) {
            $created = array_merge_recursive(...$created);
        }

        return ProductManufacturerWriteResource::createWrittenEvent($created, $context, $data, $errors);
    }

    private function createWriteContext(string $shopUuid): WriteContext
    {
        $writeContext = new WriteContext();
        $writeContext->set(ShopWriteResource::class, 'uuid', $shopUuid);

        return $writeContext;
    }

    private function getExtender(): FieldExtenderCollection
    {
        $extenderCollection = new FieldExtenderCollection();
        $extenderCollection->addExtender($this->extender);

        $event = new ProductManufacturerWriteExtenderEvent($extenderCollection);
        $this->eventDispatcher->dispatch(ProductManufacturerWriteExtenderEvent::NAME, $event);

        return $event->getExtenderCollection();
    }

    private function validateWriteInput(array $data): void
    {
        $malformedRows = [];

        foreach ($data as $index => $row) {
            if (!is_array($row)) {
                $malformedRows[] = $index;
            }
        }

        if (count($malformedRows) === 0) {
            return;
        }

        throw new \InvalidArgumentException('Expected input to be array.');
    }
}
