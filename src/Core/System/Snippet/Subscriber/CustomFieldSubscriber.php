<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('system-settings')]
class CustomFieldSubscriber implements EventSubscriberInterface
{
    private const CUSTOM_FIELD_ID_FIELD = 'custom_field_id';

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'custom_field.written' => 'customFieldIsWritten',
            'custom_field.deleted' => 'customFieldIsDeleted',
        ];
    }

    public function customFieldIsWritten(EntityWrittenEvent $event): void
    {
        $snippets = [];
        $snippetSets = null;
        foreach ($event->getWriteResults() as $writeResult) {
            if (!isset($writeResult->getPayload()['config']['label']) || empty($writeResult->getPayload()['config']['label'])) {
                continue;
            }

            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                if ($snippetSets === null) {
                    $snippetSets = $this->connection->fetchAllAssociative('SELECT id, iso FROM snippet_set');
                }

                if (empty($snippetSets)) {
                    return;
                }

                $this->setInsertSnippets($writeResult, $snippetSets, $snippets);
            }
        }

        if (empty($snippets)) {
            return;
        }

        foreach ($snippets as $snippet) {
            $this->connection->executeStatement(
                'INSERT INTO snippet (`id`, `snippet_set_id`, `translation_key`, `value`, `author`, `custom_fields`, `created_at`)
                      VALUES (:id, :setId, :translationKey, :value, :author, :customFields, :createdAt)
                      ON DUPLICATE KEY UPDATE `value` = :value',
                $snippet
            );
        }
    }

    public function customFieldIsDeleted(EntityDeletedEvent $event): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `snippet`
            WHERE JSON_EXTRACT(`custom_fields`, "$.custom_field_id") IN (:customFieldIds)',
            ['customFieldIds' => $event->getIds()],
            ['customFieldIds' => ArrayParameterType::STRING]
        );
    }

    /**
     * @param list<array<string, string>> $snippetSets
     * @param array<string, mixed> $snippets
     */
    private function setInsertSnippets(EntityWriteResult $writeResult, array $snippetSets, array &$snippets): void
    {
        $name = $writeResult->getPayload()['name'];
        $labels = $writeResult->getPayload()['config']['label'];

        foreach ($snippetSets as $snippetSet) {
            $label = $name;
            $iso = $snippetSet['iso'];

            if (isset($labels[$iso])) {
                $label = $labels[$iso];
            }

            $snippets[] = [
                'id' => Uuid::randomBytes(),
                'setId' => $snippetSet['id'],
                'translationKey' => 'customFields.' . $name,
                'value' => $label,
                'author' => 'System',
                'customFields' => json_encode([
                    self::CUSTOM_FIELD_ID_FIELD => $writeResult->getPrimaryKey(),
                ], \JSON_THROW_ON_ERROR),
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
        }
    }
}
