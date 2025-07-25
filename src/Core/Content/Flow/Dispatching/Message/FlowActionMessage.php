<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Message;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class FlowActionMessage implements AsyncMessageInterface
{
    public function __construct(public string $actionName, public StorableFlow $event)
    {
    }
}
