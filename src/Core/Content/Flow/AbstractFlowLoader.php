<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Shopware\Core\Content\Flow\SequenceTree\SequenceTreeCollection;

/**
 * @internal (FEATURE_NEXT_8225)
 */
abstract class AbstractFlowLoader
{
    abstract public function getDecorated(): AbstractFlowLoader;

    abstract public function load(string $eventName): SequenceTreeCollection;
}
