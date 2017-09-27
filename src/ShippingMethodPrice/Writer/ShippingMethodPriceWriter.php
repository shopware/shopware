<?php

namespace Shopware\ShippingMethodPrice\Writer;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEventDispatcher;
use Shopware\Framework\Write\FieldAware\DefaultExtender;
use Shopware\Framework\Write\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Write\FieldException\WriteStackException;
use Shopware\Framework\Write\WriteContext;
use Shopware\Framework\Write\Writer;
use Shopware\ShippingMethodPrice\Event\ShippingMethodPriceWriteExtenderEvent;
use Shopware\ShippingMethodPrice\Event\ShippingMethodPriceWrittenEvent;
use Shopware\ShippingMethodPrice\Writer\Resource\ShippingMethodPriceResource;
use Shopware\Shop\Writer\Resource\ShopResource;

class ShippingMethodPriceWriter
{
    /**
     * @var DefaultExtender
     */
    private $extender;

    /**
     * @var NestedEventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var Writer
     */
    private $writer;

    public function __construct(DefaultExtender $extender, NestedEventDispatcher $eventDispatcher, Writer $writer)
    {
        $this->extender = $extender;
        $this->eventDispatcher = $eventDispatcher;
        $this->writer = $writer;
    }

    public function update(array $data, TranslationContext $context): ShippingMethodPriceWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $updated = $errors = [];

        foreach ($data as $shippingMethodPrice) {
            try {
                $updated[] = $this->writer->update(
                    ShippingMethodPriceResource::class,
                    $shippingMethodPrice,
                    $writeContext,
                    $extender
                );
            } catch (WriteStackException $exception) {
                $errors[] = $exception->toArray();
            }
        }

        $affected = count($updated);
        if (1 === $affected) {
            $updated = array_shift($updated);
        } elseif ($affected > 1) {
            $updated = array_merge_recursive(...$updated);
        }

        return ShippingMethodPriceResource::createWrittenEvent($updated, $context, $errors);
    }

    public function upsert(array $data, TranslationContext $context): ShippingMethodPriceWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $created = $errors = [];

        foreach ($data as $shippingMethodPrice) {
            try {
                $created[] = $this->writer->upsert(
                    ShippingMethodPriceResource::class,
                    $shippingMethodPrice,
                    $writeContext,
                    $extender
                );
            } catch (WriteStackException $exception) {
                $errors[] = $exception->toArray();
            }
        }

        $affected = count($created);
        if (1 === $affected) {
            $created = array_shift($created);
        } elseif ($affected > 1) {
            $created = array_merge_recursive(...$created);
        }

        return ShippingMethodPriceResource::createWrittenEvent($created, $context, $errors);
    }

    public function create(array $data, TranslationContext $context): ShippingMethodPriceWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $created = $errors = [];

        foreach ($data as $shippingMethodPrice) {
            try {
                $created[] = $this->writer->insert(
                    ShippingMethodPriceResource::class,
                    $shippingMethodPrice,
                    $writeContext,
                    $extender
                );
            } catch (WriteStackException $exception) {
                $errors[] = $exception->toArray();
            }
        }

        $affected = count($created);
        if (1 === $affected) {
            $created = array_shift($created);
        } elseif ($affected > 1) {
            $created = array_merge_recursive(...$created);
        }

        return ShippingMethodPriceResource::createWrittenEvent($created, $context, $errors);
    }

    private function createWriteContext(string $shopUuid): WriteContext
    {
        $writeContext = new WriteContext();
        $writeContext->set(ShopResource::class, 'uuid', $shopUuid);

        return $writeContext;
    }

    private function getExtender(): FieldExtenderCollection
    {
        $extenderCollection = new FieldExtenderCollection();
        $extenderCollection->addExtender($this->extender);

        $event = new ShippingMethodPriceWriteExtenderEvent($extenderCollection);
        $this->eventDispatcher->dispatch(ShippingMethodPriceWriteExtenderEvent::NAME, $event);

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

        if (0 === count($malformedRows)) {
            return;
        }

        throw new \InvalidArgumentException('Expected input to be array.');
    }
}
