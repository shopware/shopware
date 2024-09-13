<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('services-settings')]
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

        $queue = new MultiInsertQueryQueue($this->connection, 500, false, false);
        $queue->addUpdateFieldOnDuplicateKey('snippet', 'value');

        foreach ($snippets as $snippet) {
            $queue->addInsert('snippet', $snippet);
        }

        $queue->execute();
    }

    public function customFieldIsDeleted(EntityDeletedEvent $event): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `snippet`
            WHERE JSON_EXTRACT(`custom_fields`, "$.custom_field_id") IN (:customFieldIds)',
            ['customFieldIds' => $event->getIds()],
            ['customFieldIds' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param array<array<string, string>> $snippetSets
     * @param list<array<string, mixed>> $snippets
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
                'snippet_set_id' => $snippetSet['id'],
                'translation_key' => 'customFields.' . $name,
                'value' => $label,
                'author' => 'System',
                'custom_fields' => json_encode([
                    self::CUSTOM_FIELD_ID_FIELD => $writeResult->getPrimaryKey(),
                ], \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
        }
    }
}
