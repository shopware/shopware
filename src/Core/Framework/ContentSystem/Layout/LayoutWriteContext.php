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
 * validator reads carries the operation key as well. The keying is sound because of one invariant of the
 * write extractor — it normalizes *every* row of a batch before extracting *any* row, and mints an absent
 * id in the primary-key pass that runs ahead of every other field's normalize. So each row's tree is
 * memoized under a key that is already final when the validator rebuilds it.
 *
 * A key holds every tree remembered under it, in the order they were remembered, and a read takes the oldest
 * remaining one. One batch may legitimately carry the same layout id twice — two Sync operations on it, or
 * two rows of one upsert array — and the DAL keeps a command per row rather than collapsing them
 * (`WriteCommandQueue::add()` appends), so both commands reach the validator and both need their own tree.
 * The pairing is sound because the two sides agree on the order: the extractor normalizes the rows of a
 * batch in payload order and then extracts them in that same order, appending one command per row, so the
 * nth command under a key reads the nth tree remembered under it.
 *
 * That one-remember-per-command correspondence is what the pairing rests on, and one extractor path can break
 * it: `WriteCommandExtractor::createDataStack()` re-normalizes a whole created row under a *cloned*
 * `WriteContext` when its definition declares defaults, so the clone would open a memo of its own and the
 * validator, holding the original, would find none — `layoutWriteMemoMissing` for every layout row of that
 * write. `ContentLayoutDefinition` declares none and the base `EntityDefinition::getDefaults()` is empty, so
 * nothing takes that path today; giving `content_layout` a default means revisiting the pairing first.
 *
 * Reads consume: an entry is removed as it is handed out, so a write that reaches the validator leaves
 * nothing behind by construction rather than by a cleanup pass.
 *
 * A memo belongs to the one write that opened it, which is what keeps the positional pairing from spanning
 * writes. The `Context` is the caller's and outlives any single write, while the `WriteContext` is minted per
 * repository call, so that instance identifies the write: {@see ownedBy()}. A write that fails before the
 * validation event fires leaves its entries behind, and without the ownership check the next write to the
 * same row would read them — it appends its own tree behind the stale one and `consume()` hands out the
 * oldest. With it, the next write replaces the memo instead, so a stale entry is unreachable and the
 * accumulation ceiling is one write's rows rather than the `Context`'s whole lifetime. Nothing leaves the
 * process either way: `Context::__serialize()` enumerates its fields explicitly and omits extensions, so a
 * memo never rides a serialized `Context` into a queued message.
 *
 * @internal
 */
#[Package('framework')]
final class LayoutWriteContext extends Struct
{
    public const EXTENSION_NAME = 'content-system-layout-write';

    /**
     * @var array<string, list<StoredTree>>
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
        $this->trees[$this->key($entityName, $primaryKey)][] = $tree;
    }

    /**
     * The oldest tree still memoized for this row, removed from the memo as it is returned. `null` means no
     * entry left, which is the normal answer for a row whose write never touched the layout column.
     */
    public function consume(string $entityName, string $primaryKey): ?StoredTree
    {
        $key = $this->key($entityName, $primaryKey);
        $queued = $this->trees[$key] ?? [];

        $tree = array_shift($queued);

        $this->trees[$key] = $queued;

        if ($queued === []) {
            unset($this->trees[$key]);
        }

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
