<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout;

use Shopware\Core\Framework\ContentSystem\Layout\Field\StoredElementListFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Validation\ContentLayoutWriteValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * The trees a layout write already decoded, carried on that write's `Context` from
 * {@see StoredElementListFieldSerializer::normalize()} to {@see ContentLayoutWriteValidator}, so the gate
 * judges the tree the write boundary produced instead of decoding the column for itself. That removes the
 * validator's decode, not every repeated one: a normal write still decodes twice, once in `normalize()` and
 * once in {@see StoredElementListFieldSerializer::encode()}. It rides the `Context` under
 * {@see EXTENSION_NAME}, the same extension seam `EntityIndexerRegistry::EXTENSION_INDEXER_SKIP` uses.
 *
 * Entries are keyed by entity name plus primary key rather than by write path, because the two sides do not
 * see the same path for one row: on a Sync write the serializer sees a bare row index while the command the
 * validator reads carries the operation key as well. The keying is sound because the write extractor mints an
 * absent id in the primary-key pass that runs ahead of every other field's normalize, so each row's tree is
 * memoized under a key that is already final when the validator rebuilds it.
 *
 * A key holds one tree, the one remembered last. One batch may write the same layout more than once, and the
 * extractor normalizes the rows of a batch in payload order, so the last tree remembered for a record is the
 * tree the batch stores for it, and the validator judges each record once, on that tree.
 *
 * `WriteCommandExtractor::createDataStack()` re-normalizes a created row under a *cloned* `WriteContext` when its
 * definition declares defaults, so the clone would open a memo of its own and the validator, holding the
 * original, would find none: `layoutWriteMemoMissing` for every layout row of that write. `ContentLayoutDefinition`
 * declares none and the base `EntityDefinition::getDefaults()` is empty, so nothing takes that path today.
 *
 * Reads consume: an entry is removed as it is handed out, so a write that reaches the validator leaves
 * nothing behind by construction rather than by a cleanup pass.
 *
 * A memo belongs to the one write that opened it. The `Context` is the caller's and outlives any single write,
 * while the `WriteContext` is minted per repository call, so that instance identifies the write: {@see ownedBy()}.
 * A write that fails before the validation event fires leaves its entries behind. With the ownership check the
 * next write replaces the memo instead of reading them, so a stale entry is unreachable and the accumulation
 * ceiling is one write's rows rather than the `Context`'s whole lifetime. Nothing leaves the process either way:
 * `Context::__serialize()` enumerates its fields explicitly and omits extensions, so a memo never rides a
 * serialized `Context` into a queued message.
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

    public function remember(string $entityName, string $primaryKey, StoredTree $tree): void
    {
        $this->trees[$this->key($entityName, $primaryKey)] = $tree;
    }

    /**
     * The tree remembered last for this row, removed from the memo as it is returned. `null` means no entry,
     * which is the normal answer for a row whose write never touched the layout column.
     */
    public function consume(string $entityName, string $primaryKey): ?StoredTree
    {
        $key = $this->key($entityName, $primaryKey);
        $tree = $this->trees[$key] ?? null;

        unset($this->trees[$key]);

        return $tree;
    }

    public function isEmpty(): bool
    {
        return $this->trees === [];
    }

    /**
     * The primary key is lower-cased because the two sides reach it differently: the serializer reads the
     * hex id straight out of the write payload, where a client may have sent it upper-cased, while the
     * command decodes it from the stored bytes and always yields lower case.
     */
    private function key(string $entityName, string $primaryKey): string
    {
        return $entityName . ':' . mb_strtolower($primaryKey);
    }
}
