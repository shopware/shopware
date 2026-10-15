<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Aggregate\DocumentType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentTypeCollection::class)]
class DocumentTypeCollectionTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyCollectionAcceptsEntities(): void
    {
        $entity = new DocumentTypeEntity();
        $entity->setUniqueIdentifier('type');
        $collection = new DocumentTypeCollection([$entity]);

        static::assertSame($entity, $collection->first());
        static::assertSame('document_type_collection', $collection->getApiAlias());
        static::assertCount(0, new DocumentTypeCollection());
    }

    /**
     * @deprecated tag:v6.9.0 - Remove with the major feature flag.
     */
    public function testEvenEmptyConstructionThrowsWhenRemovalFlagIsActive(): void
    {
        Feature::registerFeature('v6.9.0.0');
        Feature::setActive('v6.9.0.0', true);
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedClassMessage(DocumentTypeCollection::class, 'v6.9.0.0')));

        new DocumentTypeCollection();
    }

    public function testExistingCollectionMetadataRemainsCallableWhenRemovalFlagIsActive(): void
    {
        $collection = new DocumentTypeCollection();
        $entity = new DocumentTypeEntity();
        $entity->setUniqueIdentifier('type');
        Feature::registerFeature('v6.9.0.0');
        Feature::setActive('v6.9.0.0', true);
        $collection->add($entity);

        static::assertSame($entity, $collection->first());
    }
}
