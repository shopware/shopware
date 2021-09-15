<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

/**
 * @internal (flag:FEATURE_NEXT_8225) - Internal used for FlowBuilder feature
 */
abstract class AbstractFlowLoader
{
    abstract public function getDecorated(): AbstractFlowLoader;

    abstract public function load(): array;
}
