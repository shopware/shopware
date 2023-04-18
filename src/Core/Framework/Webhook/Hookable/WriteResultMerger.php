<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Hookable;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - Will be internal - reason:visibility-change
 */
#[Package('core')]
class WriteResultMerger
{
    /**
     * @internal
     */
    public function __construct(private readonly DefinitionInstanceRegistry $definitionRegistry)
    {
    }

    public function mergeWriteResults(
        EntityWrittenEvent $writtenEvent,
        ?EntityWrittenEvent $translationEvent
    ): ?EntityWrittenEvent {
        if ($writtenEvent instanceof EntityDeletedEvent) {
            return $writtenEvent;
        }

        $mergedWriteResults = [];
        foreach ($writtenEvent->getWriteResults() as $writeResult) {
            if ($translationEvent) {
                $mergedWriteResults[] = $this->getMergedWriteResult($translationEvent, $writeResult);

                continue;
            }

            if (empty($writeResult->getPayload())) {
                continue;
            }

            $mergedWriteResults[] = $writeResult;
        }

        $mergedWriteResults = array_filter($mergedWriteResults);

        if (empty($mergedWriteResults)) {
            return null;
        }

        return new EntityWrittenEvent(
            $writtenEvent->getEntityName(),
            $mergedWriteResults,
            $writtenEvent->getContext(),
            $writtenEvent->getErrors()
        );
    }

    private function getMergedWriteResult(
        EntityWrittenEvent $translationEvent,
        EntityWriteResult $writeResult
    ): ?EntityWriteResult {
        $translationResults = $this->findWriteResultByPrimaryKey(
            $translationEvent->getWriteResults(),
            $writeResult->getPrimaryKey()
        );

        $payload = $writeResult->getPayload();
        foreach ($translationResults as $translationResult) {
            $payload = array_merge($payload, $this->getMergeableTranslationPayload($translationResult));
        }

        if (empty($payload)) {
            return null;
        }

        return new EntityWriteResult(
            $writeResult->getPrimaryKey(),
            $payload,
            $writeResult->getEntityName(),
            $writeResult->getOperation(),
            $writeResult->getExistence(),
            $writeResult->getChangeSet()
        );
    }

    /**
     * @param EntityWriteResult[] $writeResults
     * @param string|array<string, string> $entityKey
     *
     * @return EntityWriteResult[]
     */
    private function findWriteResultByPrimaryKey(array $writeResults, array|string $entityKey): array
    {
        return array_filter($writeResults, static function (EntityWriteResult $result) use ($entityKey): bool {
            $primaryKey = $result->getPrimaryKey();

            if (\is_array($primaryKey)) {
                unset($primaryKey['languageId']);

                if (\count($primaryKey) === 1) {
                    $primaryKey = array_shift($primaryKey);
                }
            }

            return $primaryKey === $entityKey;
        });
    }

    /**
     * @return array<mixed>
     */
    private function getMergeableTranslationPayload(EntityWriteResult $translationResult): array
    {
        // use PKs from definition because versionIds are removed from the writeResult
        $pks = $this->definitionRegistry
            ->getByEntityName($translationResult->getEntityName())
            ->getPrimaryKeys()
            ->getKeys();

        return array_diff_key(
            $translationResult->getPayload(),
            array_flip($pks)
        );
    }
}
