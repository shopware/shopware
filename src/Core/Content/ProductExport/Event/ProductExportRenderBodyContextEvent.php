<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ProductExportRenderBodyContextEvent extends Event
{
    public const NAME = 'product_export.render.body_context';

    /** @var array */
    private $context;

    public function __construct(array $context)
    {
        $this->context = $context;
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
