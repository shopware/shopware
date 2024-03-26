<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\UsageData\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Shopware\Core\System\UsageData\Services\UsageDataAllowListService;

/**
 * @internal
 */
class EntityDefinitionServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testTaggedEntitiesOnlyHaveSinglePrimaryKey(): void
    {
        $problematicEntities = [];

        /** @var EntityDefinitionService $entityDefinitionService */
        $entityDefinitionService = $this->getContainer()->get(EntityDefinitionService::class);

        /** @var UsageDataAllowListService $usageDataAllowListService */
        $usageDataAllowListService = $this->getContainer()->get(UsageDataAllowListService::class);

        foreach ($entityDefinitionService->getAllowedEntityDefinitions() as $entityDefinition) {
            $fields = $usageDataAllowListService->getFieldsToSelectFromDefinition($entityDefinition);

            $primaryKeyCount = 0;
            foreach ($fields as $field) {
                foreach ($field->getFlags() as $flag) {
                    if ($flag instanceof PrimaryKey) {
                        ++$primaryKeyCount;
                    }
                }
            }

            if ($primaryKeyCount > 1) {
                $associationFields = $entityDefinitionService->getManyToManyAssociationIdFields($entityDefinition->getFields());
                $missingIdFields = [];

                foreach ($associationFields as $data) {
                    if (($idField = $data['idField']) !== null) {
                        $fields->add($idField);
                    } else {
                        $missingIdFields[] = $data['associationField'];
                    }
                }

                if (\count($missingIdFields) !== 0) {
                    $problematicEntities[] = $entityDefinition->getEntityName();
                }
            }
        }

        // assert with an empty array in order to get the diff in the error message
        static::assertEquals([], $problematicEntities, 'Expected that tagged entities with more than one primary key (without the VersionField) only have many-to-many associations with an corresponding ManyToManyIdField.');
    }

    public function testTaggedEntitiesHaveCreatedAndUpdatedFields(): void
    {
        $problematicEntities = [];

        /** @var EntityDefinitionService $service */
        $service = $this->getContainer()->get(EntityDefinitionService::class);

        foreach ($service->getAllowedEntityDefinitions() as $entityDefinition) {
            if (!$entityDefinition->hasCreatedAndUpdatedAtFields()) {
                $problematicEntities[] = $entityDefinition->getEntityName();
            }
        }

        // assert with an empty array in order to get the diff in the error message
        static::assertEquals([], $problematicEntities, 'Expected that tagged entities have created_at and updated_at fields.');
    }
}
