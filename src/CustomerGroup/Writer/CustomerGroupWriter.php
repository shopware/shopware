<?php

namespace Shopware\CustomerGroup\Writer;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Event\CustomerGroupWriteExtenderEvent;
use Shopware\CustomerGroup\Event\CustomerGroupWrittenEvent;
use Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource;
use Shopware\Framework\Event\NestedEventDispatcher;
use Shopware\Framework\Write\FieldAware\DefaultExtender;
use Shopware\Framework\Write\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Write\FieldException\WriteStackException;
use Shopware\Framework\Write\WriteContext;
use Shopware\Framework\Write\Writer;
use Shopware\Shop\Writer\Resource\ShopResource;

class CustomerGroupWriter
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

    public function update(array $data, TranslationContext $context): CustomerGroupWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $updated = $errors = [];

        foreach ($data as $customerGroup) {
            try {
                $updated[] = $this->writer->update(
                    CustomerGroupResource::class,
                    $customerGroup,
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

        return CustomerGroupResource::createWrittenEvent($updated, $errors);
    }

    public function upsert(array $data, TranslationContext $context): CustomerGroupWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $created = $errors = [];

        foreach ($data as $customerGroup) {
            try {
                $created[] = $this->writer->upsert(
                    CustomerGroupResource::class,
                    $customerGroup,
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

        return CustomerGroupResource::createWrittenEvent($created, $errors);
    }

    public function create(array $data, TranslationContext $context): CustomerGroupWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $created = $errors = [];

        foreach ($data as $customerGroup) {
            try {
                $created[] = $this->writer->insert(
                    CustomerGroupResource::class,
                    $customerGroup,
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

        return CustomerGroupResource::createWrittenEvent($created, $errors);
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

        $event = new CustomerGroupWriteExtenderEvent($extenderCollection);
        $this->eventDispatcher->dispatch(CustomerGroupWriteExtenderEvent::NAME, $event);

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
