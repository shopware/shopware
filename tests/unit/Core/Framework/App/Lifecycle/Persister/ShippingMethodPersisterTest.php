<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodEntity;
use Shopware\Core\Framework\App\Lifecycle\Persister\ShippingMethodPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[CoversClass(ShippingMethodPersister::class)]
class ShippingMethodPersisterTest extends TestCase
{
    private const ICON_URL = __DIR__ . '/_fixtures/Icons/TestIcon.png';

    private const APP_ID = '2b0e78aa591e11ee8c990242ac120002';

    private const DEFAULT_LOCALE_ID = '350202b740dd451db69e4bdcb76cd3b4';

    public function testUpdateShippingMethodInstallsTwoNewShippingMethodsWithBasicManifest(): void
    {
        $manifest = $this->getManifest(__DIR__ . '/_fixtures/manifest_basic.xml');
        $context = Context::createDefaultContext();

        $shippingMethodPersister = $this->createShippingMethodPersister();

        $shippingMethodPersister->updateShippingMethods($manifest, self::APP_ID, self::DEFAULT_LOCALE_ID, $context);
    }

    public function testUpdateShippingMethodInstallsOneNewUpdateOneAndDeactivatesOneShippingMethodsWithUpdateManifest(): void
    {
        $manifest = $this->getManifest(__DIR__ . '/_fixtures/update_basic.xml');
        $context = Context::createDefaultContext();

        $appShippingMethodRepositoryMock = $this->createAppShippingMethodRepositoryMockWithExistingAppShippingMethods();

        $shippingMethodRepositoryMock = $this->createMock(EntityRepository::class);
        $shippingMethodRepositoryMock->expects(static::once())->method('upsert');
        $shippingMethodRepositoryMock->expects(static::once())->method('update');

        $shippingMethodPersister = $this->createShippingMethodPersister([
            'shippingMethodRepository' => $shippingMethodRepositoryMock,
            'appShippingMethodRepository' => $appShippingMethodRepositoryMock,
            'mediaService' => $this->createMock(MediaService::class),
        ]);

        $shippingMethodPersister->updateShippingMethods($manifest, self::APP_ID, self::DEFAULT_LOCALE_ID, $context);
    }

    /**
     * @param array<string, mixed> $services
     */
    private function createShippingMethodPersister(array $services = []): ShippingMethodPersister
    {
        $deliveryTime = new DeliveryTimeEntity();
        $deliveryTime->setId('ca565fa321ad4c87a2669161907fc4c8');

        return new ShippingMethodPersister(
            \array_key_exists('shippingMethodRepository', $services) ? $services['shippingMethodRepository'] : $this->createShippingMethodRepositoryMock(),
            \array_key_exists('appShippingMethodRepository', $services) ? $services['appShippingMethodRepository'] : $this->createAppShippingMethodRepositoryMock(),
            \array_key_exists('mediaRepository', $services) ? $services['mediaRepository'] : $this->createMediaRepositoryMock(),
            \array_key_exists('mediaService', $services) ? $services['mediaService'] : $this->createMediaServiceMock(),
            \array_key_exists('sourceResolver', $services) ? $services['sourceResolver'] : new StaticSourceResolver([
                'swagUnitTestShippingMethodPersister' => new StaticFilesystem(['icons/TestIcon.png' => 'someiconblob']),
            ]),
        );
    }

    private function createShippingMethodRepositoryMock(): EntityRepository
    {
        return new StaticEntityRepository([]);
    }

    private function createAppShippingMethodRepositoryMock(): EntityRepository
    {
        $appShippingMethodMock = $this->createMock(EntityRepository::class);
        $appShippingMethodMock->method('search')->willReturn(
            new EntitySearchResult(
                AppShippingMethodEntity::class,
                0,
                new EntityCollection(),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        return $appShippingMethodMock;
    }

    private function createMediaRepositoryMock(): EntityRepository
    {
        $mediaRepositoryMock = $this->createMock(EntityRepository::class);
        $mediaRepositoryMock->method('searchIds')->willReturn(
            new IdSearchResult(
                0,
                [],
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        return $mediaRepositoryMock;
    }

    private function createMediaServiceMock(): MediaService&MockObject
    {
        $mediaServiceMock = $this->createMock(MediaService::class);
        $mediaServiceMock->expects(static::once())->method('saveFile')->willReturn(self::ICON_URL);

        return $mediaServiceMock;
    }

    private function getManifest(string $file): Manifest
    {
        static::assertTrue(is_file($file));

        return Manifest::createFromXmlFile($file);
    }

    private function createAppShippingMethodRepositoryMockWithExistingAppShippingMethods(): EntityRepository|MockObject
    {
        $shippingMethodOne = new ShippingMethodEntity();
        $shippingMethodOne->setId('40a65bae126c4d9784da11144e1fc9e3');
        $shippingMethodOne->setUniqueIdentifier('shippingMethodOne');
        $shippingMethodOne->setName('shippingMethodOne');

        $appShippingMethodOne = new AppShippingMethodEntity();
        $appShippingMethodOne->setId('0a0dc6f736b84b068ac98eed17ff9ef4');
        $appShippingMethodOne->setShippingMethod($shippingMethodOne);
        $appShippingMethodOne->setIdentifier('shippingMethodOne');

        $shippingMethodTwo = new ShippingMethodEntity();
        $shippingMethodTwo->setId('16cf9ee9b93e413faa20646258014e71');
        $shippingMethodTwo->setUniqueIdentifier('shippingMethodTwo');
        $shippingMethodTwo->setName('shippingMethodTwo');

        $appShippingMethodTwo = new AppShippingMethodEntity();
        $appShippingMethodTwo->setId('ac131e0ec3f3487fa52bd2fb31cfdf64');
        $appShippingMethodTwo->setShippingMethod($shippingMethodTwo);
        $appShippingMethodTwo->setIdentifier('shippingMethodTwo');

        $entityCollection = new EntityCollection([
            $appShippingMethodOne,
            $appShippingMethodTwo,
        ]);

        $entitySearchResultMock = $this->createMock(EntitySearchResult::class);
        $entitySearchResultMock->expects(static::once())->method('getEntities')->willReturn($entityCollection);

        $appShippingMethodRepositoryMock = $this->createMock(EntityRepository::class);
        $appShippingMethodRepositoryMock->expects(static::once())->method('search')->willReturn($entitySearchResultMock);

        return $appShippingMethodRepositoryMock;
    }
}
