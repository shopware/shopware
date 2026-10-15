<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\ImportExportProfileTranslationCollection;
use Shopware\Core\Content\ImportExport\ImportExportProfileTranslationEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ImportExportProfileTranslationCollection::class)]
class ImportExportProfileTranslationCollectionTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyCollectionAcceptsEntities(): void
    {
        $entity = new ImportExportProfileTranslationEntity();
        $entity->setUniqueIdentifier('translation');
        $collection = new ImportExportProfileTranslationCollection([$entity]);

        static::assertSame($entity, $collection->first());
        static::assertCount(0, new ImportExportProfileTranslationCollection());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testEvenEmptyConstructionThrowsInMajorMode(): void
    {
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedClassMessage(ImportExportProfileTranslationCollection::class, 'v6.8.0.0')));

        new ImportExportProfileTranslationCollection();
    }

    public function testExistingCollectionMetadataRemainsSafeInMajorMode(): void
    {
        $collection = Feature::withFeatureDisabled('v6.8.0.0', static fn () => new ImportExportProfileTranslationCollection());
        $entity = Feature::withFeatureDisabled('v6.8.0.0', static fn () => new ImportExportProfileTranslationEntity());
        $entity->setUniqueIdentifier('translation');
        $collection->add($entity);

        static::assertSame($entity, $collection->first());
    }
}
