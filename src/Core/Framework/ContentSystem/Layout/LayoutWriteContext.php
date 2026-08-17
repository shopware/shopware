<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout;

use Shopware\Core\Framework\ContentSystem\Layout\Field\StoredElementListFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Validation\ContentLayoutWriteValidator;
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
 * it: `WriteCommandExtractor::createDataStack()` re-normalizes a whole created row when its definition
 * declares defaults, which would remember a second tree for a single command and leave every later command
 * under that key reading its predecessor's. `ContentLayoutDefinition` declares none and the base
 * `EntityDefinition::getDefaults()` is empty, so nothing takes that path today. Giving `content_layout` a
 * default would, and the pairing has to be revisited then rather than discovered through a mis-gated write.
 *
 * Reads consume: an entry is removed as it is handed out, so a write that reaches the validator leaves
 * nothing behind by construction rather than by a cleanup pass.
 *
 * One residual leak is accepted: a write that fails before the validation event fires — a constraint
 * violation on another row of the same batch, say — leaves this batch's entries on the caller-owned
 * `Context`. Nothing bounds that accumulation other than the caller's `Context` lifetime: every remembered
 * tree is its own entry, so the ceiling is the number of layout rows written by writes that fail before the
 * validation event on one reused `Context`, and growth needs a long-running process that keeps failing
 * layout writes without ever recycling it. The leak cannot travel beyond that process:
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
     * @var array<string, list<StoredTree>>
     */
    private array $trees = [];

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
