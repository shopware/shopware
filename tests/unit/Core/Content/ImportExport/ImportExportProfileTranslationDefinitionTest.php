<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\ImportExportProfileTranslationCollection;
use Shopware\Core\Content\ImportExport\ImportExportProfileTranslationDefinition;
use Shopware\Core\Content\ImportExport\ImportExportProfileTranslationEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ImportExportProfileTranslationDefinition::class)]
class ImportExportProfileTranslationDefinitionTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyDefinitionMetadata(): void
    {
        $definition = new ImportExportProfileTranslationDefinition();

        static::assertSame('import_export_profile_translation', $definition->getEntityName());
        static::assertSame(ImportExportProfileTranslationCollection::class, $definition->getCollectionClass());
        static::assertSame(ImportExportProfileTranslationEntity::class, $definition->getEntityClass());
        static::assertSame('6.2.0.0', $definition->since());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testConstructionThrowsInMajorMode(): void
    {
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedClassMessage(ImportExportProfileTranslationDefinition::class, 'v6.8.0.0')));

        new ImportExportProfileTranslationDefinition();
    }

    public function testExistingMetadataRemainsCallableInMajorMode(): void
    {
        $definition = Feature::withFeatureDisabled('v6.8.0.0', static fn () => new ImportExportProfileTranslationDefinition());

        static::assertSame('import_export_profile_translation', $definition->getEntityName());
        static::assertSame(ImportExportProfileTranslationCollection::class, $definition->getCollectionClass());
        static::assertSame(ImportExportProfileTranslationEntity::class, $definition->getEntityClass());
    }
}
