<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\FlowCollection;
use Shopware\Core\Framework\Context;

/**
 * @internal API
 */
abstract class AbstractFlowLoader
{
    abstract public function getDecorated(): AbstractFlowLoader;

    abstract public function load(string $eventName, Context $context): FlowCollection;

    abstract public function reset(): void;
}
