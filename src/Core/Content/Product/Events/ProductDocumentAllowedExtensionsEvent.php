<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Lets extensions change the file types of product documents, which start as `shopware.product_documents.allowed_extensions`.
 *
 * @final
 */
#[Package('inventory')]
class ProductDocumentAllowedExtensionsEvent extends Event implements ShopwareEvent
{
    /**
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        private array $allowedExtensions,
        private readonly Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return list<string>
     */
    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    /**
     * @param list<string> $allowedExtensions
     */
    public function setAllowedExtensions(array $allowedExtensions): void
    {
        $this->allowedExtensions = $allowedExtensions;
    }
}
