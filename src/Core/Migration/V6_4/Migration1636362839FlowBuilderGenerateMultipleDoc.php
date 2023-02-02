<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1636362839FlowBuilderGenerateMultipleDoc extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1636362839;
    }

    public function update(Connection $connection): void
    {
        $actionGenerateDocs = $connection->fetchAllAssociative(
            'SELECT id, action_name, config FROM flow_sequence WHERE action_name = :actionName',
            [
                'actionName' => 'action.generate.document',
            ]
        );

        foreach ($actionGenerateDocs as $actionGenerateDoc) {
            $connection->executeStatement(
                'UPDATE flow_sequence SET config = :newConfig WHERE id = :id',
                [
                    'id' => $actionGenerateDoc['id'],
                    'newConfig' => json_encode(
                        [
                            'documentTypes' => [
                                json_decode((string) $actionGenerateDoc['config'], true, 512, \JSON_THROW_ON_ERROR),
                            ],
                        ],
                        2
                    ),
                ]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
