<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Writer;

use Shopware\Api\Write\FieldAware\DefaultExtender;
use Shopware\Api\Write\FieldAware\FieldExtenderCollection;
use Shopware\Api\Write\FieldException\WriteStackException;
use Shopware\Api\Write\ResourceWriterInterface;
use Shopware\Api\Write\WriteContext;
use Shopware\Api\Write\WriterInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEventDispatcherInterface;
use Shopware\PaymentMethod\Event\PaymentMethodWriteExtenderEvent;
use Shopware\PaymentMethod\Event\PaymentMethodWrittenEvent;
use Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class PaymentMethodWriter implements WriterInterface
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

    public function update(array $data, TranslationContext $context): PaymentMethodWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $updated = $errors = [];

        foreach ($data as $paymentMethod) {
            try {
                $updated[] = $this->writer->update(
                    PaymentMethodWriteResource::class,
                    $paymentMethod,
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

        return PaymentMethodWriteResource::createWrittenEvent($updated, $context, $data, $errors);
    }

    public function upsert(array $data, TranslationContext $context): PaymentMethodWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $created = $errors = [];

        foreach ($data as $paymentMethod) {
            try {
                $created[] = $this->writer->upsert(
                    PaymentMethodWriteResource::class,
                    $paymentMethod,
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

        return PaymentMethodWriteResource::createWrittenEvent($created, $context, $data, $errors);
    }

    public function create(array $data, TranslationContext $context): PaymentMethodWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $created = $errors = [];

        foreach ($data as $paymentMethod) {
            try {
                $created[] = $this->writer->insert(
                    PaymentMethodWriteResource::class,
                    $paymentMethod,
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

        return PaymentMethodWriteResource::createWrittenEvent($created, $context, $data, $errors);
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

        $event = new PaymentMethodWriteExtenderEvent($extenderCollection);
        $this->eventDispatcher->dispatch(PaymentMethodWriteExtenderEvent::NAME, $event);

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
