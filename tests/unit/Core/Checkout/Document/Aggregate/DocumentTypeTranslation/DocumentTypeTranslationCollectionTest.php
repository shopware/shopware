<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Aggregate\DocumentTypeTranslation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation\DocumentTypeTranslationCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation\DocumentTypeTranslationEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentTypeTranslationCollection::class)]
class DocumentTypeTranslationCollectionTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyCollectionAcceptsEntities(): void
    {
        $entity = new DocumentTypeTranslationEntity();
        $entity->setUniqueIdentifier('type');
        $collection = new DocumentTypeTranslationCollection([$entity]);

        static::assertSame($entity, $collection->first());
        static::assertSame('document_type_translation_collection', $collection->getApiAlias());
        static::assertCount(0, new DocumentTypeTranslationCollection());
    }

    /**
     * @deprecated tag:v6.9.0 - Remove with the major feature flag.
     */
    public function testEvenEmptyConstructionThrowsWhenRemovalFlagIsActive(): void
    {
        Feature::registerFeature('v6.9.0.0');
        Feature::setActive('v6.9.0.0', true);
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedClassMessage(DocumentTypeTranslationCollection::class, 'v6.9.0.0')));

        new DocumentTypeTranslationCollection();
    }

    public function testExistingCollectionMetadataRemainsCallableWhenRemovalFlagIsActive(): void
    {
        $collection = new DocumentTypeTranslationCollection();
        $entity = new DocumentTypeTranslationEntity();
        $entity->setUniqueIdentifier('type');
        Feature::registerFeature('v6.9.0.0');
        Feature::setActive('v6.9.0.0', true);
        $collection->add($entity);

        static::assertSame($entity, $collection->first());
    }
}
