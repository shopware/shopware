<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\MessageQueue\fixtures;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 */
class NoHandlerMessage implements AsyncMessageInterface
{
}
