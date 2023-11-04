<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('sales-channel')]
class ProductExportRenderHeaderContextEvent extends Event
{
    final public const NAME = 'product_export.render.header_context';

    public function __construct(private array $context)
    {
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}
