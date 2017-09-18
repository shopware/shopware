<?php

namespace Shopware\PriceGroup\Writer;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEventDispatcher;
use Shopware\Framework\Write\FieldAware\DefaultExtender;
use Shopware\Framework\Write\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Write\FieldException\WriteStackException;
use Shopware\Framework\Write\WriteContext;
use Shopware\Framework\Write\Writer;
use Shopware\PriceGroup\Event\PriceGroupWriteExtenderEvent;
use Shopware\PriceGroup\Event\PriceGroupWrittenEvent;
use Shopware\PriceGroup\Writer\Resource\PriceGroupResource;
use Shopware\Shop\Writer\Resource\ShopResource;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PriceGroupWriter
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

    public function __construct(DefaultExtender $extender, EventDispatcherInterface $eventDispatcher, Writer $writer)
    {
        $this->extender = $extender;
        $this->eventDispatcher = $eventDispatcher;
        $this->writer = $writer;
    }

    public function update(array $data, TranslationContext $context): PriceGroupWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $updated = $errors = [];

        foreach ($data as $priceGroup) {
            try {
                $updated[] = $this->writer->update(
                    PriceGroupResource::class,
                    $priceGroup,
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

        return PriceGroupResource::createWrittenEvent($updated, $errors);
    }

    public function upsert(array $data, TranslationContext $context): PriceGroupWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $created = $errors = [];

        foreach ($data as $priceGroup) {
            try {
                $created[] = $this->writer->upsert(
                    PriceGroupResource::class,
                    $priceGroup,
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

        return PriceGroupResource::createWrittenEvent($created, $errors);
    }

    public function create(array $data, TranslationContext $context): PriceGroupWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $created = $errors = [];

        foreach ($data as $priceGroup) {
            try {
                $created[] = $this->writer->insert(
                    PriceGroupResource::class,
                    $priceGroup,
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

        return PriceGroupResource::createWrittenEvent($created, $errors);
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

        $event = $this->eventDispatcher->dispatch(
            PriceGroupWriteExtenderEvent::NAME,
            new PriceGroupWriteExtenderEvent($extenderCollection)
        );

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
