<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

/**
 * @package business-ops
 *
 * @internal not intended for decoration or replacement
 */
abstract class AbstractFlowLoader
{
    abstract public function getDecorated(): AbstractFlowLoader;

    abstract public function load(): array;
}
