<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Event;

use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final class DocumentGeneratedEvent extends Event implements ShopwareEvent
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

    public function getContext(): Context
    {
        return $this->generationContext->apiContext;
    }
}
