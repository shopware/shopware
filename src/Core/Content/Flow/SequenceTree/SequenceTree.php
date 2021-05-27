<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\SequenceTree;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal (flag:FEATURE_NEXT_8225)
 * The collection of Sequence of a flow
 *
 * @method void          add(Sequence $sequence)
 * @method void          set(string $key, Sequence $sequence)
 * @method Sequence[]    getIterator()
 * @method Sequence[]    getElements()
 * @method Sequence|null get(string $key)
 * @method Sequence|null first()
 * @method Sequence|null last()
 */
class SequenceTree extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Sequence::class;
    }
}
