<?php

namespace Shopware\Api;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
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

//        $this->connection->beginTransaction();
        $this->connection->executeQuery('DELETE FROM tax');
        $this->connection->executeQuery('DELETE FROM version');
        $this->connection->executeQuery('DELETE FROM version_commit');
        $this->connection->executeQuery('DELETE FROM version_commit_data');
    }

    public function tearDown()
    {
//        $this->connection->rollBack();
    }

//    public function testVersionChangeOnInsert()
//    {
//        $uuid = Uuid::uuid4()->toString();
//        $context = TranslationContext::createDefaultContext();
//        $taxData = [
//            'id' => $uuid,
//            'name' => 'foo tax',
//            'rate' => 20,
//        ];
//
//        $this->repository->create([$taxData], $context);
//
//        $changes = $this->connection->fetchAll('SELECT * FROM version_commit WHERE entity_id = :entityId AND entity_name = "tax"', ['entityId' => Uuid::fromString($uuid)->getBytes()]);
//
//        $this->assertCount(1, $changes, sprintf('Change for entity_id "%s" was not created.', $uuid));
//
//        $change = array_shift($changes);
//
//        $taxData['versionId'] = Defaults::LIVE_VERSION;
//
//        $this->assertEquals($taxData, json_decode($change['payload'], true));
//    }

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

        $changes = $this->connection->fetchAll(
            'SELECT * FROM version_commit_data ORDER BY ai ASC',
            ['entityId' => Uuid::fromString($uuid)->getBytes()]
        );

        $this->assertCount(3, $changes, 'Change history was not written correctly. Should include: tax, tax_area_rule, tax_area_rule_translation');

        $taxChange = [
            'id' => $uuid,
            'versionId' => Defaults::LIVE_VERSION,
            'name' => 'foo tax',
            'rate' => 20,
        ];

        $taxAreaChange = [
            'id' => $ruleId,
            'versionId' => Defaults::LIVE_VERSION,
            'taxId' => $uuid,
            'taxRate' => 99,
            'active' => 1,
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
        ];

        $taxAreaTranslationChange = [
            'taxAreaRuleId' => $ruleId,
            'name' => 'required',
            'languageId' => Defaults::SHOP,
            'versionId' => Defaults::LIVE_VERSION
        ];

        $this->assertEquals($taxChange, json_decode($changes[0]['payload'], true));
        $this->assertEquals($taxAreaChange, json_decode($changes[1]['payload'], true));
        $this->assertEquals($taxAreaTranslationChange, json_decode($changes[2]['payload'], true));
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

        $versionId = $this->repository->createVersion(['id' => $uuid->toString()], $context, 'testCreateVersionWithoutRelations version');

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

        $versionId = $this->versionManager->createVersion(TaxDefinition::class, $uuid, $context, 'testCreateVersionWithSubresources version');

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

        $versionId = $this->repository->createVersion(['id' => $uuid->toString()], $context, 'testMerge version');

        $versionId = Uuid::fromString($versionId);

        $this->repository->update([[
            'id' => $uuid->toString(),
            'name' => 'new merged name',
            'versionId' => $versionId->toString()
        ]], $context);

        $this->repository->merge($versionId->toString(), $context);
    }
}
