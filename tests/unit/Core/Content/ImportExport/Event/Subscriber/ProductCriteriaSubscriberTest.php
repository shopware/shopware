<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Shopware\Core\Content\ImportExport\Event\Subscriber\ProductCriteriaSubscriber;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ProductCriteriaSubscriber::class)]
class ProductCriteriaSubscriberTest extends TestCase
{
    /**
     * @param list<array{key: string, mappedKey: string}> $mapping
     * @param list<string> $expectedExcluded
     */
    #[DataProvider('mappingProvider')]
    public function testExcludesDescriptionWhenNotMapped(array $mapping, array $expectedExcluded): void
    {
        $criteria = new Criteria();
        $this->enrich($criteria, $mapping);

        static::assertSame($expectedExcluded, $criteria->getExcludedFields());
    }

    /**
     * @return iterable<string, array{list<array{key: string, mappedKey: string}>, list<string>}>
     */
    public static function mappingProvider(): iterable
    {
        yield 'description not mapped -> excluded' => [
            [['key' => 'productNumber', 'mappedKey' => 'product_number'], ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name']],
            ['description'],
        ];
        yield 'only an unrelated field mapped -> description excluded' => [
            [['key' => 'customFields.gtin', 'mappedKey' => 'gtin']],
            ['description'],
        ];
        yield 'description mapped -> kept' => [
            [['key' => 'translations.DEFAULT.description', 'mappedKey' => 'description']],
            [],
        ];
        yield 'description mapped in a non-default language -> kept' => [
            [['key' => 'translations.de-DE.description', 'mappedKey' => 'description']],
            [],
        ];
    }

    public function testExcludesDescriptionOnTranslationsAssociationWhenLoaded(): void
    {
        $criteria = new Criteria();
        // the mapping references a translated field, so the export loads the translations association
        $criteria->addAssociation('translations');

        $this->enrich($criteria, [['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name']]);

        static::assertSame(['description'], $criteria->getExcludedFields());
        static::assertSame(['description'], $criteria->getAssociation('translations')->getExcludedFields());
    }

    public function testDoesNotAddTranslationsAssociationJustToReduceIt(): void
    {
        $criteria = new Criteria();

        $this->enrich($criteria, [['key' => 'productNumber', 'mappedKey' => 'product_number']]);

        static::assertFalse($criteria->hasAssociation('translations'));
        static::assertSame(['description'], $criteria->getExcludedFields());
    }

    public function testSkipsNonProductProfiles(): void
    {
        $criteria = new Criteria();

        $this->enrich($criteria, [['key' => 'productNumber', 'mappedKey' => 'product_number']], 'category');

        static::assertSame([], $criteria->getExcludedFields());
    }

    /**
     * @param list<array{key: string, mappedKey: string}> $mapping
     */
    private function enrich(Criteria $criteria, array $mapping, string $sourceEntity = ProductDefinition::ENTITY_NAME): void
    {
        $profile = new ImportExportProfileEntity();
        $profile->setSourceEntity($sourceEntity);

        $log = new ImportExportLogEntity();
        $log->setId('log-id');
        $log->setProfile($profile);
        $log->setConfig(['mapping' => $mapping, 'parameters' => ['includeVariants' => true]]);

        $event = new EnrichExportCriteriaEvent($criteria, $log, Context::createDefaultContext());

        (new ProductCriteriaSubscriber())->enrich($event);
    }
}
