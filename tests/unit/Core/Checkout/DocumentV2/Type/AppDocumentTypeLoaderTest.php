<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Type\AppDocumentTypeLoader;
use Shopware\Core\Framework\App\Aggregate\AppDocumentType\AppDocumentTypeCollection;
use Shopware\Core\Framework\App\Aggregate\AppDocumentType\AppDocumentTypeDefinition;
use Shopware\Core\Framework\App\Aggregate\AppDocumentType\AppDocumentTypeEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(AppDocumentTypeLoader::class)]
class AppDocumentTypeLoaderTest extends TestCase
{
    public function testReturnsEmptyArrayWhenNoAppRegistersDocumentTypes(): void
    {
        $loader = new AppDocumentTypeLoader($this->repository());

        static::assertSame([], $loader->load());
    }

    public function testAdmitsAllCoreFormatsIncludingZugferd(): void
    {
        $loader = new AppDocumentTypeLoader($this->repository(
            $this->appDocumentType('swag_certificate', ['html', 'pdf', 'zugferd_xml', 'zugferd_embedded_pdf']),
        ));

        static::assertSame(
            ['swag_certificate' => ['html', 'pdf', 'zugferd_xml', 'zugferd_embedded_pdf']],
            $loader->load(),
        );
    }

    public function testDropsTypeWhenNoSupportedFormatRemains(): void
    {
        $loader = new AppDocumentTypeLoader($this->repository(
            $this->appDocumentType('swag_certificate', ['bogus_format']),
        ));

        static::assertSame([], $loader->load());
    }

    public function testMergesTypesFromMultipleActiveApps(): void
    {
        $loader = new AppDocumentTypeLoader($this->repository(
            $this->appDocumentType('swag_certificate', ['html']),
            $this->appDocumentType('swag_warranty', ['pdf']),
        ));

        static::assertSame([
            'swag_certificate' => ['html'],
            'swag_warranty' => ['pdf'],
        ], $loader->load());
    }

    public function testMemoizesResultUntilReset(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->exactly(2))->method('search')->willReturn($this->searchResult());

        $loader = new AppDocumentTypeLoader($repository);

        $loader->load();
        $loader->load();

        $loader->reset();

        $loader->load();
    }

    public function testLoadConfigReturnsConfig(): void
    {
        $loader = new AppDocumentTypeLoader($this->repository(
            $this->appDocumentType('swag_certificate', ['html', 'pdf'], ['pageOrientation' => 'landscape', 'pageSize' => 'a4']),
        ));

        static::assertSame(
            ['pageOrientation' => 'landscape', 'pageSize' => 'a4'],
            $loader->loadConfig('swag_certificate'),
        );
    }

    public function testLoadConfigReturnsEmptyArrayForNullConfig(): void
    {
        $loader = new AppDocumentTypeLoader($this->repository(
            $this->appDocumentType('swag_certificate', ['html']),
        ));

        static::assertSame([], $loader->loadConfig('swag_certificate'));
    }

    public function testLoadConfigReturnsEmptyArrayForUnknownType(): void
    {
        $loader = new AppDocumentTypeLoader($this->repository());

        static::assertSame([], $loader->loadConfig('unknown_type'));
    }

    public function testLoadAndLoadConfigShareOneMemoizedQuery(): void
    {
        $loader = new AppDocumentTypeLoader($this->repository(
            $this->appDocumentType('swag_certificate', ['html'], ['foo' => 'bar']),
        ));

        static::assertSame(['swag_certificate' => ['html']], $loader->load());
        static::assertSame(['foo' => 'bar'], $loader->loadConfig('swag_certificate'));
    }

    /**
     * @param list<string>|null $formats
     * @param array<string, scalar>|null $config
     */
    private function appDocumentType(string $technicalName, ?array $formats, ?array $config = null): AppDocumentTypeEntity
    {
        $id = Uuid::randomHex();

        $appDocumentType = new AppDocumentTypeEntity();
        $appDocumentType->setUniqueIdentifier($id);
        $appDocumentType->setId($id);
        $appDocumentType->setTechnicalName($technicalName);
        $appDocumentType->setFormats($formats);
        $appDocumentType->setConfig($config);

        return $appDocumentType;
    }

    /**
     * @return StaticEntityRepository<AppDocumentTypeCollection>
     */
    private function repository(AppDocumentTypeEntity ...$appDocumentTypes): StaticEntityRepository
    {
        return StaticEntityRepository::of(
            AppDocumentTypeCollection::class,
            [new AppDocumentTypeCollection($appDocumentTypes)],
        );
    }

    /**
     * @return EntitySearchResult<AppDocumentTypeCollection>
     */
    private function searchResult(AppDocumentTypeEntity ...$appDocumentTypes): EntitySearchResult
    {
        $collection = new AppDocumentTypeCollection($appDocumentTypes);

        return new EntitySearchResult(
            AppDocumentTypeDefinition::ENTITY_NAME,
            $collection->count(),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
