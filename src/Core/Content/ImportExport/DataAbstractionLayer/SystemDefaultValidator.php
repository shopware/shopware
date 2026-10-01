<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ImportExport\Exception\DeleteDefaultProfileException;
use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\SystemDefaultValidatorTest
 */
#[Package('fundamentals@after-sales')]
class SystemDefaultValidator implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    /**
     * @internal
     *
     * @throws DeleteDefaultProfileException
     */
    public function preValidate(PreWriteValidationEvent $event): void
    {
        $ids = array_column($event->getDeletedPrimaryKeys(ImportExportProfileDefinition::ENTITY_NAME), 'id');

        $filteredIds = $this->filterSystemDefaults($ids);
        if ($filteredIds !== []) {
            $event->getExceptions()->add(new DeleteDefaultProfileException($filteredIds));
        }
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function filterSystemDefaults(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $result = $this->connection->executeQuery(
            'SELECT id FROM import_export_profile WHERE id IN (:idList) AND system_default = 1',
            ['idList' => $ids],
            ['idList' => ArrayParameterType::BINARY]
        );

        return $result->fetchFirstColumn();
    }
}
