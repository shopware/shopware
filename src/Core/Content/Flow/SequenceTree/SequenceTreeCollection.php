<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\SequenceTree;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal (flag:FEATURE_NEXT_8225)
 * The collection of Sequence of multiple flows triggered by the same event
 *
 * @method void              add(SequenceTree $sequence)
 * @method void              set(string $key, SequenceTree $sequence)
 * @method SequenceTree[]    getIterator()
 * @method SequenceTree[]    getElements()
 * @method SequenceTree|null get(string $key)
 * @method SequenceTree|null first()
 * @method SequenceTree|null last()
 */
class SequenceTreeCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return SequenceTree::class;
    }
}
