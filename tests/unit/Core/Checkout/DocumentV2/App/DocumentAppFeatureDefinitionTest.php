<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\App;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\App\AppDocumentTypeConfig;
use Shopware\Core\Checkout\DocumentV2\App\DocumentAppFeatureDefinition;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeCollection;
use Shopware\Core\System\NumberRange\NumberRangeCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentAppFeatureDefinition::class)]
class DocumentAppFeatureDefinitionTest extends TestCase
{
    private DocumentAppFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new DocumentAppFeatureDefinition(
            static::createStub(Connection::class),
            StaticEntityRepository::of(NumberRangeTypeCollection::class),
            StaticEntityRepository::of(NumberRangeCollection::class),
        );
    }

    public function testGetTypeReturnsDocument(): void
    {
        static::assertSame('document', $this->definition->getType());
    }

    public function testGetConfigClassReturnsAppDocumentTypeConfig(): void
    {
        static::assertSame(AppDocumentTypeConfig::class, $this->definition->getConfigClass());
    }

    public function testFromAppMapsDeclaredDocumentTypesAndBackfillsMissingDefaultLocale(): void
    {
        $manifest = $this->manifest($this->documentTypes());

        $configs = $this->definition->fromApp($manifest, new Filesystem(''), 'en-GB');
        static::assertCount(2, $configs);

        $warranty = $configs[0];
        static::assertSame('swag_warranty', $warranty->getName());
        static::assertSame(['html', 'pdf'], $warranty->getFormats());
        static::assertSame(['en-GB' => 'Warranty certificate', 'de-DE' => 'Garantieschein'], $warranty->getLabel());
        static::assertSame(
            [
                'pageSize' => 'A4',
                'itemsPerPage' => 10,
                'displayHeader' => true,
                'displayFooter' => false,
                'filenamePrefix' => 'warranty',
            ],
            $warranty->getConfig()
        );

        $certificate = $configs[1];
        static::assertSame('swag_certificate', $certificate->getName());
        static::assertSame(['de-DE' => 'Zertifikat', 'en-GB' => 'Zertifikat'], $certificate->getLabel());
        static::assertSame([], $certificate->getConfig());
    }

    public function testFromAppBackfillsDefaultLocaleFromEnglishTranslationWhenShopDefaultDiffers(): void
    {
        $manifest = $this->manifest($this->documentTypes());

        $configs = $this->definition->fromApp($manifest, new Filesystem(''), 'fr-FR');
        $warranty = $configs[0];

        static::assertSame('swag_warranty', $warranty->getName());
        static::assertSame(
            ['en-GB' => 'Warranty certificate', 'de-DE' => 'Garantieschein', 'fr-FR' => 'Warranty certificate'],
            $warranty->getLabel()
        );
    }

    public function testFromAppReturnsEmptyListWhenManifestDeclaresNoDocuments(): void
    {
        $manifest = $this->manifest();

        static::assertSame([], $this->definition->fromApp($manifest, new Filesystem(''), 'en-GB'));
    }

    public function testToPayloadAndFromPayloadRoundTrip(): void
    {
        $config = new AppDocumentTypeConfig(
            'swag_warranty',
            ['html', 'pdf'],
            ['en-GB' => 'Warranty certificate', 'de-DE' => 'Garantieschein'],
            ['pageSize' => 'A4', 'itemsPerPage' => 10, 'displayHeader' => true]
        );

        $payload = $this->definition->toPayload($config, null);

        static::assertSame([
            'identifier' => 'swag_warranty',
            'formats' => ['html', 'pdf'],
            'label' => ['en-GB' => 'Warranty certificate', 'de-DE' => 'Garantieschein'],
            'config' => ['pageSize' => 'A4', 'itemsPerPage' => 10, 'displayHeader' => true],
        ], $payload);

        $restored = $this->definition->fromPayload($payload);

        static::assertSame($config->getName(), $restored->getName());
        static::assertSame($config->getFormats(), $restored->getFormats());
        static::assertSame($config->getLabel(), $restored->getLabel());
        static::assertSame($config->getConfig(), $restored->getConfig());
    }

    public function testValidateThrowsWhenIdentifierShadowsCoreDocumentType(): void
    {
        $definition = $this->buildDefinition(
            claimedBy: [],
            typeRepository: StaticEntityRepository::of(NumberRangeTypeCollection::class),
            rangeRepository: StaticEntityRepository::of(NumberRangeCollection::class),
        );

        $this->expectExceptionObject(DocumentV2Exception::documentTypeShadowsCoreType('invoice'));

        $definition->validate([$this->config('invoice')], $this->persistContext());
    }

    public function testValidateThrowsWhenIdentifierIsClaimedByAnotherApp(): void
    {
        $definition = $this->buildDefinition(
            claimedBy: ['swag_warranty' => 'OtherApp'],
            typeRepository: StaticEntityRepository::of(NumberRangeTypeCollection::class),
            rangeRepository: StaticEntityRepository::of(NumberRangeCollection::class),
        );

        $this->expectExceptionObject(DocumentV2Exception::documentTypeAlreadyRegistered('swag_warranty', 'OtherApp'));

        $definition->validate([$this->config('swag_warranty')], $this->persistContext());
    }

    public function testValidateSkipsTheCollisionQueryWhenNoConfigsAreDeclared(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        $definition = new DocumentAppFeatureDefinition(
            $connection,
            StaticEntityRepository::of(NumberRangeTypeCollection::class),
            StaticEntityRepository::of(NumberRangeCollection::class),
        );

        $definition->validate([], $this->persistContext());
    }

    public function testPersistedSeedsANumberRangeForADeclaredAppType(): void
    {
        $typeRepository = StaticEntityRepository::of(NumberRangeTypeCollection::class, [[]]);
        $rangeRepository = StaticEntityRepository::of(NumberRangeCollection::class, [[]]);

        $definition = $this->buildDefinition(claimedBy: [], typeRepository: $typeRepository, rangeRepository: $rangeRepository);
        $definition->persisted([$this->config('swag_warranty')], $this->persistContext());

        static::assertCount(1, $typeRepository->creates);

        $type = $typeRepository->creates[0][0];
        static::assertSame('document_swag_warranty', $type['technicalName']);
        static::assertSame('swag_warranty', $type['typeName']);
        static::assertTrue($type['global']);

        static::assertCount(1, $rangeRepository->creates);

        $range = $rangeRepository->creates[0][0];
        static::assertSame($type['id'], $range['typeId']);
        static::assertSame('swag_warranty', $range['name']);
        static::assertSame('{n}', $range['pattern']);
        static::assertSame(1000, $range['start']);
        static::assertTrue($range['global']);
    }

    public function testPersistedIsIdempotentWhenBothTypeAndRangeAlreadyExist(): void
    {
        $typeRepository = StaticEntityRepository::of(NumberRangeTypeCollection::class, [[Uuid::randomHex()]]);
        $rangeRepository = StaticEntityRepository::of(NumberRangeCollection::class, [[Uuid::randomHex()]]);

        $definition = $this->buildDefinition(claimedBy: [], typeRepository: $typeRepository, rangeRepository: $rangeRepository);
        $definition->persisted([$this->config('swag_warranty')], $this->persistContext());

        static::assertSame([], $typeRepository->creates);
        static::assertSame([], $rangeRepository->creates);
    }

    public function testPersistedRecreatesAMissingRangeWhenTheTypeAlreadyExists(): void
    {
        $existingTypeId = Uuid::randomHex();
        $typeRepository = StaticEntityRepository::of(NumberRangeTypeCollection::class, [[$existingTypeId]]);
        $rangeRepository = StaticEntityRepository::of(NumberRangeCollection::class, [[]]);

        $definition = $this->buildDefinition(claimedBy: [], typeRepository: $typeRepository, rangeRepository: $rangeRepository);
        $definition->persisted([$this->config('swag_warranty')], $this->persistContext());

        static::assertSame([], $typeRepository->creates);
        static::assertCount(1, $rangeRepository->creates);

        $range = $rangeRepository->creates[0][0];
        static::assertSame($existingTypeId, $range['typeId']);
        static::assertSame('swag_warranty', $range['name']);
    }

    public function testValidateThrowsWhenIdentifierIsTheReservedAppProvidedSentinel(): void
    {
        $definition = $this->buildDefinition(
            claimedBy: [],
            typeRepository: StaticEntityRepository::of(NumberRangeTypeCollection::class),
            rangeRepository: StaticEntityRepository::of(NumberRangeCollection::class),
        );

        $this->expectExceptionObject(DocumentV2Exception::documentTypeReservedIdentifier('app_provided'));

        $definition->validate([$this->config('app_provided')], $this->persistContext());
    }

    public function testPersistedDoesNothingWhenNoConfigsAreDeclared(): void
    {
        $typeRepository = StaticEntityRepository::of(NumberRangeTypeCollection::class);
        $rangeRepository = StaticEntityRepository::of(NumberRangeCollection::class);

        $definition = $this->buildDefinition(claimedBy: [], typeRepository: $typeRepository, rangeRepository: $rangeRepository);
        $definition->persisted([], $this->persistContext());

        static::assertSame([], $typeRepository->creates);
        static::assertSame([], $rangeRepository->creates);
    }

    /**
     * @param array<string, string> $claimedBy
     * @param StaticEntityRepository<NumberRangeTypeCollection> $typeRepository
     * @param StaticEntityRepository<NumberRangeCollection> $rangeRepository
     */
    private function buildDefinition(array $claimedBy, StaticEntityRepository $typeRepository, StaticEntityRepository $rangeRepository): DocumentAppFeatureDefinition
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn($claimedBy);

        return new DocumentAppFeatureDefinition($connection, $typeRepository, $rangeRepository);
    }

    private function persistContext(): AppPersistContext
    {
        return AppFixture::createInstallContext(
            AppFixture::createAppEntity('DocumentApp'),
            $this->manifest(),
        );
    }

    private function config(string $identifier): AppDocumentTypeConfig
    {
        return new AppDocumentTypeConfig($identifier, ['html'], ['en-GB' => 'Test type'], []);
    }

    private function documentTypes(): string
    {
        return <<<'XML'
            <documents>
                <document-type>
                    <identifier>swag_warranty</identifier>
                    <label>Warranty certificate</label>
                    <label lang="de-DE">Garantieschein</label>
                    <formats>
                        <format>html</format>
                        <format>pdf</format>
                    </formats>
                    <config>
                        <page-size>A4</page-size>
                        <items-per-page>10</items-per-page>
                        <display-header>true</display-header>
                        <display-footer>false</display-footer>
                        <filename-prefix>warranty</filename-prefix>
                    </config>
                </document-type>
                <document-type>
                    <identifier>swag_certificate</identifier>
                    <label lang="de-DE">Zertifikat</label>
                    <formats>
                        <format>pdf</format>
                    </formats>
                </document-type>
            </documents>
            XML;
    }

    private function manifest(string $documents = ''): Manifest
    {
        return Manifest::createFromXml(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                      xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
                <meta>
                    <name>testDocumentFeature</name>
                    <label>Swag App Document Feature Test</label>
                    <author>shopware AG</author>
                    <copyright>(c) by shopware AG</copyright>
                    <version>1.0.0</version>
                    <license>MIT</license>
                </meta>
                {$documents}
            </manifest>
            XML);
    }
}
