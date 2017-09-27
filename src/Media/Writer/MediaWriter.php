<?php

namespace Shopware\Media\Writer;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEventDispatcher;
use Shopware\Framework\Write\FieldAware\DefaultExtender;
use Shopware\Framework\Write\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Write\FieldException\WriteStackException;
use Shopware\Framework\Write\WriteContext;
use Shopware\Framework\Write\Writer;
use Shopware\Media\Event\MediaWriteExtenderEvent;
use Shopware\Media\Event\MediaWrittenEvent;
use Shopware\Media\Writer\Resource\MediaResource;
use Shopware\Shop\Writer\Resource\ShopResource;

class MediaWriter
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

    public function update(array $data, TranslationContext $context): MediaWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $updated = $errors = [];

        foreach ($data as $media) {
            try {
                $updated[] = $this->writer->update(
                    MediaResource::class,
                    $media,
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

        return MediaResource::createWrittenEvent($updated, $context, $errors);
    }

    public function upsert(array $data, TranslationContext $context): MediaWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $created = $errors = [];

        foreach ($data as $media) {
            try {
                $created[] = $this->writer->upsert(
                    MediaResource::class,
                    $media,
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

        return MediaResource::createWrittenEvent($created, $context, $errors);
    }

    public function create(array $data, TranslationContext $context): MediaWrittenEvent
    {
        $writeContext = $this->createWriteContext($context->getShopUuid());
        $extender = $this->getExtender();

        $this->validateWriteInput($data);

        $created = $errors = [];

        foreach ($data as $media) {
            try {
                $created[] = $this->writer->insert(
                    MediaResource::class,
                    $media,
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

        return MediaResource::createWrittenEvent($created, $context, $errors);
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

        $event = new MediaWriteExtenderEvent($extenderCollection);
        $this->eventDispatcher->dispatch(MediaWriteExtenderEvent::NAME, $event);

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
