<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;

interface NameAware extends FlowEventAware
{
    public const EVENT_NAME = 'name';

    public function getName(): string;
}
