<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Aggregate\DocumentType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentTypeDefinition::class)]
class DocumentTypeDefinitionTest extends TestCase
{
    public function testMetadataRemainsCallableWithPendingRemoval(): void
    {
        $definition = new DocumentTypeDefinition();

        static::assertSame('document_type', $definition->getEntityName());
        static::assertSame(DocumentTypeCollection::class, $definition->getCollectionClass());
        static::assertSame(DocumentTypeEntity::class, $definition->getEntityClass());
        static::assertSame('6.0.0.0', $definition->since());
    }

    /**
     * @deprecated tag:v6.9.0 - Remove with the major feature flag.
     */
    public function testConstructionThrowsWhenRemovalFlagIsActive(): void
    {
        Feature::registerFeature('v6.9.0.0');
        Feature::setActive('v6.9.0.0', true);
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedClassMessage(DocumentTypeDefinition::class, 'v6.9.0.0')));

        new DocumentTypeDefinition();
    }

    public function testExistingMetadataRemainsCallableWhenRemovalFlagIsActive(): void
    {
        $definition = new DocumentTypeDefinition();
        Feature::registerFeature('v6.9.0.0');
        Feature::setActive('v6.9.0.0', true);

        static::assertSame('document_type', $definition->getEntityName());
        static::assertSame(DocumentTypeCollection::class, $definition->getCollectionClass());
        static::assertSame(DocumentTypeEntity::class, $definition->getEntityClass());
    }
}
