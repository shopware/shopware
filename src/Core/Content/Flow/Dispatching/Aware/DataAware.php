<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;

interface DataAware extends FlowEventAware
{
    public const DATA = 'data';

    /**
     * @return array<string, mixed>
     */
    public function getData(): array;
}
