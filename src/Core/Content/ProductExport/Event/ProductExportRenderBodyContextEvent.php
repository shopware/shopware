<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package sales-channel
 */
class ProductExportRenderBodyContextEvent extends Event
{
    final public const NAME = 'product_export.render.body_context';

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
