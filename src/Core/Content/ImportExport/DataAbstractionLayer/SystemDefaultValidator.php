<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Content\ImportExport\Exception\DeleteDefaultProfileException;
use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Validation\ConstraintViolationExceptionInterface;
use Shopware\Core\Framework\Validation\WriteCommandValidatorInterface;

class SystemDefaultValidator implements WriteCommandValidatorInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param WriteCommandInterface[] $writeCommands
     *
     * @throws DeleteDefaultProfileException
     */
    public function preValidate(array $writeCommands, WriteContext $context): void
    {
        $ids = [];
        foreach ($writeCommands as $command) {
            if ($command->getDefinition()->getClass() === ImportExportProfileDefinition::class
                && $command instanceof DeleteCommand
            ) {
                $ids[] = $command->getPrimaryKey()['id'];
            }
        }

        $filteredIds = $this->filterSystemDefaults($ids);
        if (!empty($filteredIds)) {
            throw new DeleteDefaultProfileException($filteredIds);
        }
    }

    /**
     * @param WriteCommandInterface[] $writeCommands
     *
     * @throws ConstraintViolationExceptionInterface
     */
    public function postValidate(array $writeCommands, WriteContext $context): void
    {
        // Nothing
    }

    private function filterSystemDefaults(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $result = $this->connection->executeQuery(
            'SELECT id FROM import_export_profile WHERE id IN (:idList) AND system_default = 1',
            [':idList' => $ids],
            [':idList' => Connection::PARAM_STR_ARRAY]
        );

        return $result->fetchAll(FetchMode::COLUMN);
    }
}
