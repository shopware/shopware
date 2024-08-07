<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeMappingDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\FieldType\DateInterval;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntity;

/**
 * @internal
 */
class AttributeEntityIntegrationTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getContainer()->get(Connection::class)->commit();

        try {
            $this->getContainer()->get(Connection::class)->executeStatement(
                (string) file_get_contents(__DIR__ . '/fixture/schema.sql')
            );
        } catch (\Exception $e) {
            // restart transaction, otherwise follow-up tests will fail
            $this->getContainer()->get(Connection::class)->beginTransaction();
            static::fail($e->getMessage());
        }

        $this->getContainer()->get(Connection::class)->beginTransaction();
    }

    public function testDefinitionsExist(): void
    {
        // registered in config/services_test.xml
        static::assertTrue($this->getContainer()->has('attribute_entity.repository'));
        static::assertTrue($this->getContainer()->has('attribute_entity.definition'));

        static::assertTrue($this->getContainer()->has('attribute_entity_agg.repository'));
        static::assertTrue($this->getContainer()->has('attribute_entity_agg.definition'));

        static::assertTrue($this->getContainer()->has('attribute_entity_currency.definition'));

        static::assertTrue($this->getContainer()->has('attribute_entity_translation.repository'));
        static::assertTrue($this->getContainer()->has('attribute_entity_translation.definition'));

        static::assertInstanceOf(AttributeEntityDefinition::class, $this->getContainer()->get('attribute_entity.definition'));
        static::assertInstanceOf(AttributeEntityDefinition::class, $this->getContainer()->get('attribute_entity_agg.definition'));
        static::assertInstanceOf(AttributeMappingDefinition::class, $this->getContainer()->get('attribute_entity_currency.definition'));
        static::assertInstanceOf(AttributeTranslationDefinition::class, $this->getContainer()->get('attribute_entity_translation.definition'));

        static::assertInstanceOf(EntityRepository::class, $this->getContainer()->get('attribute_entity.repository'));
        static::assertInstanceOf(EntityRepository::class, $this->getContainer()->get('attribute_entity_currency.repository'));
        static::assertInstanceOf(EntityRepository::class, $this->getContainer()->get('attribute_entity_agg.repository'));
        static::assertInstanceOf(EntityRepository::class, $this->getContainer()->get('attribute_entity_translation.repository'));
    }

    public function testCrudRoot(): void
    {
        $ids = new IdsCollection();

        $context = Context::createDefaultContext();

        $repository = $this->repository('attribute_entity');
        $result = $repository->create([
            [
                'id' => $ids->create('first-key'),
                'string' => 'string',
                'transString' => 'transString',
                'differentName' => 'storageString',
            ],
        ], $context);

        static::assertNotEmpty($result->getPrimaryKeys('attribute_entity'));

        $written = $result->getPrimaryKeys('attribute_entity');
        static::assertContains($ids->get('first-key'), $written);

        $search = $this->repository('attribute_entity')
            ->search(new Criteria($ids->getList(['first-key'])), $context);

        static::assertCount(1, $search);
        static::assertTrue($search->has($ids->get('first-key')));

        $record = $search->get($ids->get('first-key'));

        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertSame($ids->get('first-key'), $record->id);
        static::assertSame('string', $record->string);
        static::assertSame('transString', $record->getTranslation('transString'));
        static::assertSame('storageString', $record->differentName);

        $result = $this->repository('attribute_entity')->update([
            [
                'id' => $ids->get('first-key'),
                'string' => 'string-updated',
                'transString' => 'transString-updated',
                'differentName' => 'storageString-updated',
            ],
        ], $context);

        static::assertNotEmpty($result->getPrimaryKeys('attribute_entity'));

        $written = $result->getPrimaryKeys('attribute_entity');
        static::assertContains($ids->get('first-key'), $written);

        $search = $this->repository('attribute_entity')
            ->search(new Criteria($ids->getList(['first-key'])), $context);

        static::assertCount(1, $search);
        static::assertTrue($search->has($ids->get('first-key')));

        $record = $search->get($ids->get('first-key'));

        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertSame($ids->get('first-key'), $record->id);
        static::assertSame('string-updated', $record->string);
        static::assertSame('transString-updated', $record->getTranslation('transString'));
        static::assertSame('storageString-updated', $record->differentName);

        $result = $this->repository('attribute_entity')->delete([
            ['id' => $ids->get('first-key')],
        ], $context);

        static::assertNotEmpty($result->getDeletedPrimaryKeys('attribute_entity'));

        $deleted = $result->getDeletedPrimaryKeys('attribute_entity');
        static::assertContains($ids->get('first-key'), $deleted);

        $search = $this->repository('attribute_entity')
            ->search(new Criteria($ids->getList(['first-key'])), $context);

        static::assertCount(0, $search);
    }

    public function testScalarValues(): void
    {
        $ids = new IdsCollection();

        $data = [
            'id' => $ids->get('first-key'),
            'string' => 'string',
            'text' => 'text',
            'int' => 1,
            'float' => 1.1,
            'bool' => true,
            'datetime' => new \DateTimeImmutable('2020-01-01 15:15:15'),
            'date' => new \DateTimeImmutable('2020-01-01 00:00:00'),
            'dateInterval' => new \DateInterval('P1D'),
            'timeZone' => 'Europe/Berlin',
            'json' => ['key' => 'value'],
            'serialized' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => true],
            ],
            'differentName' => 'string',
            'transString' => 'string',
            'transText' => 'text',
            'transInt' => 1,
            'transFloat' => 1.1,
            'transBool' => true,
            'transDatetime' => new \DateTimeImmutable('2020-01-01 15:15:15'),
            'transDate' => new \DateTimeImmutable('2020-01-01 00:00:00'),
            'transDateInterval' => new \DateInterval('P1D'),
            'transTimeZone' => 'Europe/Berlin',
            'transJson' => ['key' => 'value'],
        ];

        $result = $this->repository('attribute_entity')->create([$data], Context::createDefaultContext());

        static::assertNotEmpty($result->getPrimaryKeys('attribute_entity'));
        $written = $result->getPrimaryKeys('attribute_entity');
        static::assertContains($ids->get('first-key'), $written);

        $search = $this->repository('attribute_entity')
            ->search(new Criteria([$ids->get('first-key')]), Context::createDefaultContext());

        static::assertCount(1, $search);
        static::assertTrue($search->has($ids->get('first-key')));

        $record = $search->get($ids->get('first-key'));

        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertEquals('string', $record->string);
        static::assertEquals('text', $record->text);
        static::assertEquals(1, $record->int);
        static::assertEquals(1.1, $record->float);
        static::assertTrue($record->bool);
        static::assertEquals(new \DateTimeImmutable('2020-01-01 15:15:15'), $record->datetime);
        static::assertEquals(new \DateTimeImmutable('2020-01-01 00:00:00'), $record->date);
        static::assertEquals(new DateInterval('P1D'), $record->dateInterval);
        static::assertEquals('Europe/Berlin', $record->timeZone);
        static::assertEquals(['key' => 'value'], $record->json);
        static::assertEquals(
            new PriceCollection([new Price(Defaults::CURRENCY, 1, 1, true)]),
            $record->serialized
        );

        static::assertEquals('string', $record->transString);
        static::assertEquals('text', $record->transText);
        static::assertEquals(1, $record->transInt);
        static::assertEquals(1.1, $record->transFloat);
        static::assertTrue($record->transBool);
        static::assertEquals(new \DateTimeImmutable('2020-01-01 15:15:15'), $record->transDatetime);
        static::assertEquals(new \DateTimeImmutable('2020-01-01 00:00:00'), $record->transDate);
        static::assertEquals(new DateInterval('P1D'), $record->transDateInterval);
        static::assertEquals('Europe/Berlin', $record->transTimeZone);
        static::assertEquals(['key' => 'value'], $record->transJson);
        static::assertEquals('string', $record->differentName);

        $json = $record->jsonSerialize();
        unset($json['extensions']);

        static::assertEquals([
            '_uniqueIdentifier' => $ids->get('first-key'),
            'versionId' => null,
            'translated' => $record->getTranslated(),
            'createdAt' => $record->getCreatedAt()?->format(\DateTime::RFC3339_EXTENDED),
            'updatedAt' => null,
            'id' => $ids->get('first-key'),
            'string' => 'string',
            'text' => 'text',
            'int' => 1,
            'float' => 1.1,
            'bool' => true,
            'datetime' => $record->datetime?->format(\DateTime::RFC3339_EXTENDED),
            'autoIncrement' => 1,
            'json' => ['key' => 'value'],
            'date' => $record->date?->format(\DateTime::RFC3339_EXTENDED),
            'dateInterval' => new DateInterval('P1D'),
            'timeZone' => 'Europe/Berlin',
            'serialized' => new PriceCollection([new Price(Defaults::CURRENCY, 1, 1, true)]),
            'transString' => 'string',
            'transText' => 'text',
            'transInt' => 1,
            'transFloat' => 1.1,
            'transBool' => true,
            'transDatetime' => $record->transDatetime?->format(\DateTime::RFC3339_EXTENDED),
            'transJson' => ['key' => 'value'],
            'transDate' => $record->transDate?->format(\DateTime::RFC3339_EXTENDED),
            'transDateInterval' => new DateInterval('P1D'),
            'transTimeZone' => 'Europe/Berlin',
            'differentName' => 'string',
            'currencyId' => null,
            'followId' => null,
            'currency' => null,
            'follow' => null,
            'aggs' => null,
            'currencies' => null,
            'translations' => null,
            'customFields' => null,
        ], $json);
    }

    public function testOneToOne(): void
    {
        $ids = new IdsCollection();

        $data = [
            'id' => $ids->get('first-key'),
            'string' => 'string',
            'transString' => 'transString',
            'follow' => self::currency($ids->get('currency-1'), 'ABC'),
        ];

        $result = $this->repository('attribute_entity')->create([$data], Context::createDefaultContext());

        static::assertNotEmpty($result->getPrimaryKeys('attribute_entity'));
        static::assertContains($ids->get('first-key'), $result->getPrimaryKeys('attribute_entity'));

        static::assertNotEmpty($result->getPrimaryKeys('currency'));
        static::assertContains($ids->get('currency-1'), $result->getPrimaryKeys('currency'));

        $search = $this->repository('attribute_entity')
            ->search(new Criteria([$ids->get('first-key')]), Context::createDefaultContext());

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertNull($record->follow);

        $criteria = new Criteria([$ids->get('first-key')]);
        $criteria->addAssociation('follow');
        $search = $this->repository('attribute_entity')
            ->search($criteria, Context::createDefaultContext());

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertNotNull($record->follow);
        static::assertEquals($ids->get('currency-1'), $record->follow->getId());

        // test on delete set null
        $this->getContainer()->get('currency.repository')->delete([
            ['id' => $ids->get('currency-1')],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$ids->get('first-key')]);
        $criteria->addAssociation('follow');
        $search = $this->repository('attribute_entity')
            ->search($criteria, Context::createDefaultContext());

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertNull($record->follow);
        static::assertNull($record->followId);
    }

    public function testOneToMany(): void
    {
        $ids = new IdsCollection();

        $data = [
            'id' => $ids->get('first-key'),
            'string' => 'string',
            'transString' => 'transString',
            'aggs' => [
                ['id' => $ids->get('agg-1'), 'number' => 'agg-1'],
                ['id' => $ids->get('agg-2'), 'number' => 'agg-2'],
            ],
        ];

        $result = $this->repository('attribute_entity')->create([$data], Context::createDefaultContext());

        static::assertNotEmpty($result->getPrimaryKeys('attribute_entity'));
        static::assertContains($ids->get('first-key'), $result->getPrimaryKeys('attribute_entity'));

        static::assertNotEmpty($result->getPrimaryKeys('attribute_entity_agg'));
        static::assertContains($ids->get('agg-1'), $result->getPrimaryKeys('attribute_entity_agg'));
        static::assertContains($ids->get('agg-2'), $result->getPrimaryKeys('attribute_entity_agg'));

        $search = $this->repository('attribute_entity')
            ->search(new Criteria([$ids->get('first-key')]), Context::createDefaultContext());

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertNull($record->aggs);

        $criteria = new Criteria([$ids->get('first-key')]);
        $criteria->addAssociation('aggs');
        $search = $this->repository('attribute_entity')
            ->search($criteria, Context::createDefaultContext());

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertNotNull($record->aggs);
        static::assertCount(2, $record->aggs);
        static::assertArrayHasKey($ids->get('agg-1'), $record->aggs);
        static::assertArrayHasKey($ids->get('agg-2'), $record->aggs);

        $this->repository('attribute_entity_agg')->delete([
            ['id' => $ids->get('agg-1')],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$ids->get('first-key')]);
        $criteria->addAssociation('aggs');
        $search = $this->repository('attribute_entity')
            ->search($criteria, Context::createDefaultContext());

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertNotNull($record->aggs);
        static::assertCount(1, $record->aggs);
        static::assertArrayHasKey($ids->get('agg-2'), $record->aggs);

        // test cascade delete
        $deleted = $this->repository('attribute_entity')->delete([
            ['id' => $ids->get('first-key')],
        ], Context::createDefaultContext());

        static::assertContains($ids->get('first-key'), $deleted->getDeletedPrimaryKeys('attribute_entity'));
        static::assertContains($ids->get('agg-2'), $deleted->getDeletedPrimaryKeys('attribute_entity_agg'));
    }

    public function testManyToOne(): void
    {
        $ids = new IdsCollection();

        $data = [
            'id' => $ids->get('first-key'),
            'string' => 'string',
            'transString' => 'transString',
            'currency' => self::currency($ids->get('currency-1'), 'ABC'),
        ];

        $result = $this->repository('attribute_entity')->create([$data], Context::createDefaultContext());

        static::assertNotEmpty($result->getPrimaryKeys('attribute_entity'));
        static::assertContains($ids->get('first-key'), $result->getPrimaryKeys('attribute_entity'));

        static::assertNotEmpty($result->getPrimaryKeys('currency'));
        static::assertContains($ids->get('currency-1'), $result->getPrimaryKeys('currency'));

        $search = $this->repository('attribute_entity')
            ->search(new Criteria([$ids->get('first-key')]), Context::createDefaultContext());

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertNull($record->currency);

        $criteria = new Criteria([$ids->get('first-key')]);
        $criteria->addAssociation('currency');
        $search = $this->repository('attribute_entity')
            ->search($criteria, Context::createDefaultContext());

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertNotNull($record->currency);
        static::assertEquals($ids->get('currency-1'), $record->currency->getId());

        // set currencyId to null
        $result = $this->repository('attribute_entity')->update([
            [
                'id' => $ids->get('first-key'),
                'currencyId' => null,
            ],
        ], Context::createDefaultContext());

        static::assertNotEmpty($result->getPrimaryKeys('attribute_entity'));
        static::assertContains($ids->get('first-key'), $result->getPrimaryKeys('attribute_entity'));

        $criteria = new Criteria([$ids->get('first-key')]);
        $criteria->addAssociation('currency');
        $search = $this->repository('attribute_entity')
            ->search($criteria, Context::createDefaultContext());

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertNull($record->currency);

        // set currencyId again
        $result = $this->repository('attribute_entity')->update([
            [
                'id' => $ids->get('first-key'),
                'currencyId' => $ids->get('currency-1'),
            ],
        ], Context::createDefaultContext());

        static::assertNotEmpty($result->getPrimaryKeys('attribute_entity'));
        static::assertContains($ids->get('first-key'), $result->getPrimaryKeys('attribute_entity'));

        $criteria = new Criteria([$ids->get('first-key')]);
        $criteria->addAssociation('currency');
        $search = $this->repository('attribute_entity')
            ->search($criteria, Context::createDefaultContext());

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertNotNull($record->currency);
        static::assertEquals($ids->get('currency-1'), $record->currency->getId());

        // test restrict delete
        static::expectException(ForeignKeyConstraintViolationException::class);

        $this->getContainer()->get('currency.repository')->delete([
            ['id' => $ids->get('currency-1')],
        ], Context::createDefaultContext());
    }

    public function testManyToMany(): void
    {
        $ids = new IdsCollection();

        $data = [
            'id' => $ids->get('first-key'),
            'string' => 'string',
            'transString' => 'transString',
            'currencies' => [
                self::currency($ids->get('currency-1'), 'ABC'),
                self::currency($ids->get('currency-2'), 'DEF'),
            ],
        ];

        $result = $this->repository('attribute_entity')
            ->create([$data], Context::createDefaultContext());

        static::assertNotEmpty($result->getPrimaryKeys('attribute_entity'));
        static::assertContains($ids->get('first-key'), $result->getPrimaryKeys('attribute_entity'));

        static::assertNotEmpty($result->getPrimaryKeys('currency'));
        static::assertContains($ids->get('currency-1'), $result->getPrimaryKeys('currency'));
        static::assertContains($ids->get('currency-2'), $result->getPrimaryKeys('currency'));

        $search = $this->repository('attribute_entity')
            ->search(new Criteria([$ids->get('first-key')]), Context::createDefaultContext());

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertNull($record->currencies);

        $criteria = new Criteria([$ids->get('first-key')]);
        $criteria->addAssociation('currencies');
        $search = $this->repository('attribute_entity')
            ->search($criteria, Context::createDefaultContext());

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertNotNull($record->currencies);
        static::assertCount(2, $record->currencies);
        static::assertArrayHasKey($ids->get('currency-1'), $record->currencies);
        static::assertArrayHasKey($ids->get('currency-2'), $record->currencies);

        $this->repository('attribute_entity_currency')->delete([
            ['attributeEntityId' => $ids->get('first-key'), 'currencyId' => $ids->get('currency-1')],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$ids->get('first-key')]);
        $criteria->addAssociation('currencies');
        $search = $this->repository('attribute_entity')
            ->search($criteria, Context::createDefaultContext());

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertNotNull($record->currencies);
        static::assertCount(1, $record->currencies);
        static::assertArrayHasKey($ids->get('currency-2'), $record->currencies);
    }

    public function testTranslations(): void
    {
        $ids = new IdsCollection();

        $data = [
            'id' => $ids->get('first-key'),
            'string' => 'string',
            'transString' => [
                'en-GB' => 'transString',
                'de-DE' => 'transString-de',
            ],
        ];

        $result = $this->repository('attribute_entity')->create([$data], Context::createDefaultContext());

        static::assertNotEmpty($result->getPrimaryKeys('attribute_entity'));
        static::assertContains($ids->get('first-key'), $result->getPrimaryKeys('attribute_entity'));

        $context = Context::createDefaultContext();
        $search = $this->repository('attribute_entity')
            ->search(new Criteria([$ids->get('first-key')]), $context);

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertEquals('transString', $record->getTranslation('transString'));
        // translation association was not loaded in the criteria
        static::assertEmpty($record->translations);

        $criteria = new Criteria([$ids->get('first-key')]);
        $criteria->addAssociation('translations');
        $languageContext = new Context(
            $context->getSource(),
            $context->getRuleIds(),
            $context->getCurrencyId(),
            [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM],
        );
        $search = $this->repository('attribute_entity')
            ->search($criteria, $languageContext);

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertEquals('transString-de', $record->getTranslation('transString'));
        $translations = $record->translations ?? [];
        static::assertCount(2, $translations);

        foreach ($translations as $translation) {
            static::assertInstanceOf(ArrayEntity::class, $translation);
            if ($translation->get('languageId') === Defaults::LANGUAGE_SYSTEM) {
                static::assertEquals('transString', $translation->get('transString'));
            } else {
                static::assertEquals('transString-de', $translation->get('transString'));
            }
        }
    }

    public function testCustomFields(): void
    {
        $ids = new IdsCollection();

        $data = [
            'id' => $ids->get('first-key'),
            'string' => 'string',
            'transString' => 'transString',
        ];

        $result = $this->repository('attribute_entity')->create([$data], Context::createDefaultContext());

        static::assertNotEmpty($result->getPrimaryKeys('attribute_entity'));
        static::assertContains($ids->get('first-key'), $result->getPrimaryKeys('attribute_entity'));

        $context = Context::createDefaultContext();
        $search = $this->repository('attribute_entity')
            ->search(new Criteria([$ids->get('first-key')]), $context);

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertEmpty($record->getCustomFields());

        $result = $this->repository('attribute_entity')->update([
            [
                'id' => $ids->get('first-key'),
                'customFields' => [
                    'foo' => 'bar',
                    'bar' => 'baz',
                ],
            ],
        ], Context::createDefaultContext());

        $search = $this->repository('attribute_entity')
            ->search(new Criteria([$ids->get('first-key')]), $context);

        $record = $search->get($ids->get('first-key'));
        static::assertInstanceOf(AttributeEntity::class, $record);
        static::assertEquals([
            'foo' => 'bar',
            'bar' => 'baz',
        ], $record->getCustomFields());
        static::assertEquals('bar', $record->getCustomFieldsValue('foo'));
        static::assertEquals('baz', $record->getCustomFieldsValue('bar'));
    }

    private function repository(string $entity): EntityRepository
    {
        $repository = $this->getContainer()->get($entity . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    /**
     * @return array<string, mixed>
     */
    private static function currency(string $id, string $iso): array
    {
        return [
            'id' => $id,
            'name' => 'currency-' . $id,
            'factor' => 1.5,
            'symbol' => '€',
            'isoCode' => $iso,
            'shortName' => 'Euro',
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
        ];
    }
}
