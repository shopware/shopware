<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout;

use Shopware\Core\Framework\ContentSystem\Layout\Field\StoredElementListFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Validation\ContentLayoutWriteValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * The trees a layout write encoded for storage, carried on that write's `Context` from
 * {@see StoredElementListFieldSerializer::encode()} to {@see ContentLayoutWriteValidator}, so the gate judges the
 * tree the write produced instead of decoding the column for itself. It rides the `Context` under
 * {@see EXTENSION_NAME}, the same extension seam `EntityIndexerRegistry::EXTENSION_INDEXER_SKIP` uses.
 *
 * Each tree is keyed by the exact column value the serializer yields for it. The validator looks a record up by
 * the column value the record holds after the batch, so the tree it judges is the tree behind the stored bytes,
 * whatever order the DAL runs one batch's writes of the same record in. Rows that yield the same value share one
 * entry, and either tree encodes to those bytes.
 *
 * The memo also holds the trees of rows a later write of the same record overwrites; the validator never looks
 * those up. Nothing removes an entry: a lookup only reads, so when the DAL retries the write's transaction and
 * dispatches the validation event again without encoding again, the gate finds the same trees and reaches the
 * same verdict.
 *
 * A memo belongs to the one write that opened it, retries included. The `Context` is the caller's and outlives
 * any single write, while the `WriteContext` is minted per repository call, so that instance identifies the
 * write: {@see ownedBy()}. The memo stays on the `Context` after its write, and the next write on the same
 * `Context` replaces it instead of reading it, so an entry of an earlier write is unreachable and the
 * accumulation ceiling is one write's rows rather than the `Context`'s whole lifetime. Nothing leaves the process
 * either way: `Context::__serialize()` enumerates its fields explicitly and omits extensions, so a memo never
 * rides a serialized `Context` into a queued message.
 *
 * @internal
 */
#[Package('framework')]
final class LayoutWriteContext extends Struct
{
    public const EXTENSION_NAME = 'content-system-layout-write';

    /**
     * @var array<string, StoredTree>
     */
    private array $trees = [];

    public function __construct(private readonly WriteContext $owner)
    {
    }

    /**
     * Whether this memo was opened by the write now asking for it. A `Context` reused across writes carries at
     * most one memo, so the answer decides between reading it and replacing it.
     */
    public function ownedBy(WriteContext $writeContext): bool
    {
        return $this->owner === $writeContext;
    }

    public function remember(string $encoded, StoredTree $tree): void
    {
        $this->trees[$encoded] = $tree;
    }

    /**
     * The tree remembered for this column value, `null` when the write encoded no such value.
     */
    public function treeFor(string $encoded): ?StoredTree
    {
        return $this->trees[$encoded] ?? null;
    }
}
