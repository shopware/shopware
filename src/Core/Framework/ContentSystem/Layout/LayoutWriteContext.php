<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout;

use Shopware\Core\Framework\ContentSystem\Layout\Field\StoredElementListFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Validation\ContentLayoutWriteValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * The trees layout writes encoded for storage, carried on the writes' `Context` from
 * {@see StoredElementListFieldSerializer::encode()} to {@see ContentLayoutWriteValidator}, so the gate judges the
 * tree a write produced instead of decoding the column for itself. It rides the `Context` under
 * {@see EXTENSION_NAME}, the same extension seam `EntityIndexerRegistry::EXTENSION_INDEXER_SKIP` uses.
 *
 * Each tree is keyed by the write's `WriteContext` and then by the exact column value the serializer yields for
 * it. The validator looks a record up by the column value the record holds after the batch, so the tree it judges
 * is the tree behind the stored bytes, whatever order the DAL runs one batch's writes of the same record in. Rows
 * of one write that yield the same value share one entry, and either tree encodes to those bytes.
 *
 * The entries of a write also hold the trees of rows a later row of the same record overwrites; the validator
 * never looks those up. Nothing removes an entry: a lookup only reads, so when the DAL retries the write's
 * transaction in the same `WriteContext` and dispatches the validation event again without encoding again, the
 * gate finds the same trees and reaches the same verdict.
 *
 * Entries belong to their write. The `Context` is the caller's and outlives any single write, while the
 * `WriteContext` is minted per repository call, so that instance identifies the write. A second write on the same
 * `Context`, such as one a listener starts while the first write is still between command extraction and its
 * validation event, keeps its entries apart and never touches the first write's. The entries are held weakly by
 * their `WriteContext`, so they go away with it and do not accumulate over the `Context`'s lifetime. Nothing
 * leaves the process either way: `Context::__serialize()` enumerates its fields explicitly and omits extensions,
 * so a memo never rides a serialized `Context` into a queued message.
 *
 * @internal
 */
#[Package('framework')]
final class LayoutWriteContext extends Struct
{
    public const EXTENSION_NAME = 'content-system-layout-write';

    /**
     * @var \WeakMap<WriteContext, array<string, StoredTree>>
     */
    private \WeakMap $trees;

    public function __construct()
    {
        $this->trees = new \WeakMap();
    }

    public function remember(WriteContext $write, string $encoded, StoredTree $tree): void
    {
        $trees = $this->trees[$write] ?? [];
        $trees[$encoded] = $tree;
        $this->trees[$write] = $trees;
    }

    /**
     * The tree this write remembered for this column value, `null` when the write encoded no such value.
     */
    public function treeFor(WriteContext $write, string $encoded): ?StoredTree
    {
        return ($this->trees[$write] ?? [])[$encoded] ?? null;
    }
}
