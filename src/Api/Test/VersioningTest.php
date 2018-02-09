<?php

namespace Shopware\Api;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Tax\Definition\TaxAreaRuleDefinition;
use Shopware\Api\Tax\Definition\TaxAreaRuleTranslationDefinition;
use Shopware\Api\Tax\Definition\TaxDefinition;
use Shopware\Api\Tax\Repository\TaxRepository;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Defaults;
use Shopware\Version\VersionManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class VersioningTest extends KernelTestCase
{
    /**
     * @var TaxRepository
     */
    private $repository;

    /**
     * @var Connection
     */
    private $connection;

    public function setUp()
    {
        $kernel = static::bootKernel();

        $this->repository = $kernel->getContainer()->get(TaxRepository::class);
        $this->connection = $kernel->getContainer()->get('dbal_connection');
        $this->connection->beginTransaction();
    }

    public function tearDown()
    {
        $this->connection->rollBack();
    }

    public function testVersionChangeOnInsert()
    {
        $uuid = Uuid::uuid4()->toString();
        $context = TranslationContext::createDefaultContext();
        $taxData = [
            'id' => $uuid,
            'name' => 'foo tax',
            'rate' => 20,
        ];

        $this->repository->create([$taxData], $context);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes, sprintf('Change for entity_id "%s" was not created.', $uuid));

        $change = array_shift($changes);

        $taxData['versionId'] = Defaults::LIVE_VERSION;

        $this->assertEquals($taxData, json_decode($change['payload'], true));
    }

    public function testVersionChangeOnInsertWithSubresources()
    {
        $uuid = Uuid::uuid4()->toString();
        $ruleId = Uuid::uuid4()->toString();

        $context = TranslationContext::createDefaultContext();
        $taxData = [
            'id' => $uuid,
            'name' => 'foo tax',
            'rate' => 20,
            'areaRules' => [
                [
                    'id' => $ruleId,
                    'taxRate' => 99,
                    'active' => true,
                    'name' => 'required',
                    'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP
                ],
            ]
        ];

        $this->repository->create([$taxData], $context);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);
        $taxChange = [
            'id' => $uuid,
            'versionId' => Defaults::LIVE_VERSION,
            'name' => 'foo tax',
            'rate' => 20,
        ];
        $this->assertEquals($taxChange, json_decode($changes[0]['payload'], true));



        $changes = $this->getVersionData(TaxAreaRuleDefinition::getEntityName(), $ruleId, Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);
        $taxAreaChange = [
            'id' => $ruleId,
            'versionId' => Defaults::LIVE_VERSION,
            'taxId' => $uuid,
            'taxRate' => 99,
            'active' => 1,
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
        ];
        $this->assertEquals($taxAreaChange, json_decode($changes[0]['payload'], true));


        $changes = $this->getTranslationVersionData(TaxAreaRuleTranslationDefinition::getEntityName(), Defaults::SHOP, 'taxAreaRuleId', $ruleId, Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);
        $taxAreaTranslationChange = [
            'taxAreaRuleId' => $ruleId,
            'name' => 'required',
            'languageId' => Defaults::SHOP,
            'versionId' => Defaults::LIVE_VERSION
        ];
        $this->assertEquals($taxAreaTranslationChange, json_decode($changes[0]['payload'], true));
    }

    public function testCreateNewVersion()
    {
        $uuid = Uuid::uuid4();
        $context = TranslationContext::createDefaultContext();
        $taxData = [
            'id' => $uuid->toString(),
            'name' => 'foo tax',
            'rate' => 20,
        ];

        $this->repository->create([$taxData], $context);

        $versionId = $this->repository->createVersion($uuid->toString(), $context, 'testCreateVersionWithoutRelations version');

        $this->assertNotEmpty($versionId);

        $versionId = Uuid::fromString($versionId);

        $tax = $this->connection->fetchAssoc(
            'SELECT * FROM tax WHERE id = :id AND version_id = :versionId',
            [
                'id' => $uuid->getBytes(),
                'versionId' => $versionId->getBytes()
            ]
        );

        $this->assertNotFalse($tax, 'Tax clone was not created.');

        $this->assertEquals($uuid->getBytes(), $tax['id']);
        $this->assertEquals($versionId->getBytes(), $tax['version_id']);
        $this->assertEquals('foo tax', $tax['name']);
        $this->assertEquals(20, $tax['tax_rate']);
    }

    public function testCreateNewVersionWithSubresources()
    {
        $uuid = Uuid::uuid4();
        $ruleId = Uuid::uuid4();
        $context = TranslationContext::createDefaultContext();

        $taxData = [
            'id' => $uuid->toString(),
            'name' => 'foo tax',
            'rate' => 20,
            'areaRules' => [
                [
                    'id' => $ruleId->toString(),
                    'taxRate' => 99,
                    'active' => true,
                    'name' => 'required',
                    'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP
                ],
            ]
        ];

        $this->repository->create([$taxData], $context);

        $versionId = $this->repository->createVersion($uuid->toString(), $context, 'testCreateVersionWithSubresources version');

        $this->assertNotEmpty($versionId);

        $versionId = Uuid::fromString($versionId);

        $tax = $this->connection->fetchAssoc(
            'SELECT * FROM tax WHERE id = :id AND version_id = :versionId',
            [
                'id' => $uuid->getBytes(),
                'versionId' => $versionId->getBytes()
            ]
        );

        $taxRules = $this->connection->fetchAll(
            'SELECT * FROM tax_area_rule WHERE tax_id = :id AND version_id = :versionId',
            [
                'id' => $uuid->getBytes(),
                'versionId' => $versionId->getBytes()
            ]
        );

        $this->assertNotFalse($tax, 'Tax clone was not created.');
        $this->assertCount(1, $taxRules, 'Tax area rule clones were not created.');

        $this->assertEquals($uuid->getBytes(), $tax['id']);
        $this->assertEquals($versionId->getBytes(), $tax['version_id']);
        $this->assertEquals('foo tax', $tax['name']);
        $this->assertEquals(20, $tax['tax_rate']);

        $this->assertEquals($uuid->getBytes(), $taxRules[0]['tax_id']);
        $this->assertEquals($versionId->getBytes(), $taxRules[0]['version_id']);
    }

    public function testMergeVersions()
    {
        $uuid = Uuid::uuid4();
        $context = TranslationContext::createDefaultContext();
        $taxData = ['id' => $uuid->toString(), 'name' => 'foo tax', 'rate' => 20];

        $this->repository->create([$taxData], $context);

        $versionId = $this->repository->createVersion($uuid->toString(), $context, 'testMerge version');

        $versionId = Uuid::fromString($versionId);

        $this->repository->update([[
            'id' => $uuid->toString(),
            'name' => 'new merged name',
            'versionId' => $versionId->toString()
        ]], $context);

        $this->assertNotEmpty(
            $this->getVersionData(TaxDefinition::getEntityName(), $uuid->toString(), $versionId->toString())
        );

        $this->repository->merge($versionId->toString(), $context);

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes()
        ]);

        $this->assertEquals('new merged name', $row['name']);

        $row = $this->connection->fetchAssoc('SELECT * FROM version WHERE id = :id', ['id' => $versionId->getBytes()]);
        $this->assertEmpty($row);

        $row = $this->connection->fetchAssoc('SELECT * FROM version_commit WHERE version_id = :id', ['id' => $versionId->getBytes()]);
        $this->assertEmpty($row);

        $this->assertEmpty(
            $this->getVersionData(TaxDefinition::getEntityName(), $uuid->toString(), $versionId->toString())
        );
    }

    private function getVersionData(string $entity, string $id, string $versionId): array
    {
        return $this->connection->fetchAll(
            "SELECT * 
             FROM version_commit_data 
             WHERE entity_name = :entity 
             AND JSON_EXTRACT(entity_id, '$.id') = :id
             AND JSON_EXTRACT(entity_id, '$.versionId') = :version",
            [
                'entity' => $entity,
                'id' => $id,
                'version' => $versionId
            ]
        );
    }

    private function getTranslationVersionData(string $entity, string $languageId, string $foreignKeyName, string $foreignKey, string $versionId): array
    {
        return $this->connection->fetchAll(
            "SELECT * 
             FROM version_commit_data 
             WHERE entity_name = :entity 
             AND JSON_EXTRACT(entity_id, '$.".$foreignKeyName."') = :id
             AND JSON_EXTRACT(entity_id, '$.languageId') = :language
             AND JSON_EXTRACT(entity_id, '$.versionId') = :version",
            [
                'entity' => $entity,
                'id' => $foreignKey,
                'language' => $languageId,
                'version' => $versionId
            ]
        );
    }
}

