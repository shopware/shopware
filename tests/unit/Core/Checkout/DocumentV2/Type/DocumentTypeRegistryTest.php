<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Type\AppDocumentTypeLoader;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Framework\App\Aggregate\AppDocumentType\AppDocumentTypeCollection;
use Shopware\Core\Framework\App\Aggregate\AppDocumentType\AppDocumentTypeEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentType;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentTypeRegistry::class)]
class DocumentTypeRegistryTest extends TestCase
{
    public function testGetDocumentTypesAndFormats(): void
    {
        $registry = new DocumentTypeRegistry([
            new StaticDocumentType('invoice', ['html', 'pdf']),
            new StaticDocumentType('delivery_note', ['html']),
        ], $this->appDocumentTypeLoader());

        static::assertSame(['invoice', 'delivery_note'], $registry->getDocumentTypes());
        static::assertSame(['html', 'pdf'], $registry->getSupportedFormats('invoice'));
        static::assertTrue($registry->supports('invoice'));
        static::assertFalse($registry->supports('credit_note'));
        static::assertSame([], $registry->getSupportedFormats('credit_note'));
    }

    public function testFormatsAreUnionMergedAcrossDefinitions(): void
    {
        $registry = new DocumentTypeRegistry([
            new StaticDocumentType('delivery_note', ['html', 'pdf']),
            new StaticDocumentType('delivery_note', ['pdf', 'zugferd_xml']),
        ], $this->appDocumentTypeLoader());

        static::assertSame(['html', 'pdf', 'zugferd_xml'], $registry->getSupportedFormats('delivery_note'));
    }

    public function testValidateFormatsPassesForSupported(): void
    {
        $registry = new DocumentTypeRegistry([new StaticDocumentType('invoice', ['html', 'pdf'])], $this->appDocumentTypeLoader());

        $registry->validateFormats('invoice', ['html', 'pdf']);

        static::assertTrue($registry->supports('invoice'));
    }

    public function testValidateFormatsThrowsForUnsupported(): void
    {
        $registry = new DocumentTypeRegistry([new StaticDocumentType('invoice', ['html'])], $this->appDocumentTypeLoader());

        $this->expectExceptionObject(DocumentV2Exception::unsupportedDocumentFormat('pdf', 'invoice'));

        $registry->validateFormats('invoice', ['pdf']);
    }

    public function testAppRegisteredTypeAppearsInRegistry(): void
    {
        $registry = new DocumentTypeRegistry(
            [new StaticDocumentType('invoice', ['html', 'pdf'])],
            $this->appDocumentTypeLoader(['swag_certificate' => ['html', 'pdf']]),
        );

        static::assertSame(['invoice', 'swag_certificate'], $registry->getDocumentTypes());
        static::assertSame(['html', 'pdf'], $registry->getSupportedFormats('swag_certificate'));
        static::assertTrue($registry->supports('swag_certificate'));
    }

    public function testAppRegisteredTypeDoesNotOverrideCoreTypeOfSameName(): void
    {
        $registry = new DocumentTypeRegistry(
            [new StaticDocumentType('invoice', ['html', 'pdf'])],
            $this->appDocumentTypeLoader(['invoice' => ['pdf']]),
        );

        static::assertSame(['html', 'pdf'], $registry->getSupportedFormats('invoice'));
    }

    public function testResetInvalidatesMemoizedMergeSoUpdatedAppTypesAreReflected(): void
    {
        $loader = new AppDocumentTypeLoader(StaticEntityRepository::of(AppDocumentTypeCollection::class, [
            $this->appDocumentTypes(['swag_certificate' => ['html']]),
            $this->appDocumentTypes(['swag_warranty' => ['pdf']]),
        ]));

        $registry = new DocumentTypeRegistry([new StaticDocumentType('invoice', ['html', 'pdf'])], $loader);

        static::assertSame(['invoice', 'swag_certificate'], $registry->getDocumentTypes());
        // Memoized: a second read before reset must not requery, and must still reflect the old app type.
        static::assertSame(['invoice', 'swag_certificate'], $registry->getDocumentTypes());

        $registry->reset();
        $loader->reset();

        static::assertSame(['invoice', 'swag_warranty'], $registry->getDocumentTypes());
    }

    /**
     * @param array<string, list<string>> $appTypes
     */
    private function appDocumentTypeLoader(array $appTypes = []): AppDocumentTypeLoader
    {
        return new AppDocumentTypeLoader(StaticEntityRepository::of(
            AppDocumentTypeCollection::class,
            [$this->appDocumentTypes($appTypes)],
        ));
    }

    /**
     * @param array<string, list<string>> $appTypes
     */
    private function appDocumentTypes(array $appTypes): AppDocumentTypeCollection
    {
        $entities = [];

        foreach ($appTypes as $identifier => $formats) {
            $id = Uuid::randomHex();

            $entity = new AppDocumentTypeEntity();
            $entity->setUniqueIdentifier($id);
            $entity->setId($id);
            $entity->setTechnicalName($identifier);
            $entity->setFormats($formats);
            $entity->setConfig(null);

            $entities[] = $entity;
        }

        return new AppDocumentTypeCollection($entities);
    }
}
