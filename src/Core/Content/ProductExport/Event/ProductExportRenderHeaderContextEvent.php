<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('inventory')]
class ProductExportRenderHeaderContextEvent extends Event
{
    final public const NAME = 'product_export.render.header_context';

    /**
     * @param array<string, Struct> $context
     */
    public function __construct(private array $context)
    {
    }

    /**
     * @return array<string, Struct>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param array<string, Struct> $context
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}
