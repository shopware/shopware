<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Event;

use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationContext;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('after-sales')]
final class DocumentGeneratedEvent extends Event implements GenericEvent
{
    final public const NAME = 'document.generation';

    public function __construct(
        private readonly DocumentEntity $document,
        private readonly DocumentGenerationContext $generationContext,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDocument(): DocumentEntity
    {
        return $this->document;
    }

    public function getGenerationContext(): DocumentGenerationContext
    {
        return $this->generationContext;
    }
}
