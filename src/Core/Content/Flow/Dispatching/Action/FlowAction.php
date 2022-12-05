<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;

/**
 * @package business-ops
 *
 * @internal
 */
abstract class FlowAction
{
    /**
     * @return array<int, string>
     */
    abstract public function requirements(): array;

    abstract public function handleFlow(StorableFlow $flow): void;

    abstract public static function getName(): string;
}
