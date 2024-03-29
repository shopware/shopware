<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Lifecycle\Persister\ShippingMethodPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ShippingMethodPersisterTest extends TestCase
{
    use ExtensionBehaviour;
    use IntegrationTestBehaviour;

    private const APP_NAME = 'test';
    private const APP_PATH = __DIR__ . '/../_fixtures/shippingMethodBase/test';
    private const ACTIVE_FLAG_APP_PATH = __DIR__ . '/../_fixtures/ShippingMethodActiveFlag/test';
    private const MANIFEST_UPDATE = __DIR__ . '/../_fixtures/shippingMethodUpdate/test/manifest.xml';
    private const MANIFEST_UPDATE_IDENTIFIER = __DIR__ . '/../_fixtures/shippingMethodUpdateIdentifier/test/manifest.xml';
    private const IDENTIFIER_FIRST_METHOD = 'swagFirstShippingMethod';
    private const IDENTIFIER_SECOND_METHOD = 'swagSecondShippingMethod';
    private const IDENTIFIER_SECOND_METHOD_UPDATED = 'swagUpdatedShippingMethod';
    private const INITIAL_METHOD_NAME = 'first Shipping Method';
    private const INITIAL_METHOD_DESCRIPTION = 'This is a simple description';
    private const INITIAL_TECHNICAL_NAME = 'shipping_test_swagFirstShippingMethod';
    private const DELIVERY_TIME_ID_INITIAL = '4b00146bdc8b4175b12d3fc36ec114c8';
    private const TRACKING_URL_UPDATED = 'https://www.mytrackingurl.com/updated-by-manifest';
    private const POSITION_INITIAL = 3;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
    }

    public function testUpdateShippingMethodsAmountShallNotChangeAfterUpdate(): void
    {
        $this->installApp(self::APP_PATH);

        $expectedShippingMethodCountBeforeUpdate = 4;
        $expectedAppShippingMethodCountBeforeUpdate = 2;
        $expectedShippingMethodCountAfterUpdate = 4;
        $expectedAppShippingMethodCountAfterUpdate = 2;

        $numberOfShippingMethods = $this->getNumberOfShippingMethods();
        static::assertSame($expectedShippingMethodCountBeforeUpdate, $numberOfShippingMethods, 'Number of expected shipping methods before update does not match');
        $numberOfAppShippingMethods = $this->getNumberOfShippingMethods(true);
        static::assertSame($expectedAppShippingMethodCountBeforeUpdate, $numberOfAppShippingMethods, 'Number of expected app shipping methods before update does not match');

        $this->updateApp($this->getAppId(), self::MANIFEST_UPDATE);

        $numberOfShippingMethodsAfterUpdate = $this->getNumberOfShippingMethods();
        static::assertSame($expectedShippingMethodCountAfterUpdate, $numberOfShippingMethodsAfterUpdate, 'Number of expected shipping methods after update does not match');
        $numberOfAppShippingMethodsAfterUpdate = $this->getNumberOfShippingMethods(true);
        static::assertSame($expectedAppShippingMethodCountAfterUpdate, $numberOfAppShippingMethodsAfterUpdate, 'Number of expected app shipping methods after update does not match');

        $this->removeApp(self::APP_PATH);
    }

    public function testUpdateShippingMethodsShallNotUpdatePropertiesThatCanBeChangedByMerchant(): void
    {
        $this->installApp(self::APP_PATH);

        $appShippingMethods = $this->getApp()->getAppShippingMethods();
        static::assertInstanceOf(EntityCollection::class, $appShippingMethods);
        $shippingMethod = $appShippingMethods->filterByProperty('identifier', self::IDENTIFIER_FIRST_METHOD)->first()?->getShippingMethod();
        static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
        $mediaId = $shippingMethod->getMediaId();

        $this->updateApp($this->getAppId(), self::MANIFEST_UPDATE);

        $updatedAppShippingMethods = $this->getApp()->getAppShippingMethods();
        static::assertInstanceOf(EntityCollection::class, $updatedAppShippingMethods);
        $updatedShippingMethod = $updatedAppShippingMethods->filterByProperty('identifier', self::IDENTIFIER_FIRST_METHOD)->first()?->getShippingMethod();
        static::assertInstanceOf(ShippingMethodEntity::class, $updatedShippingMethod);

        static::assertSame(self::INITIAL_TECHNICAL_NAME, $updatedShippingMethod->getTechnicalName(), 'technicalName shall be not updatable by app but has been updated.');
        static::assertSame(self::INITIAL_METHOD_NAME, $updatedShippingMethod->getName(), 'name shall be not updatable by app but has been updated.');
        static::assertSame(self::INITIAL_METHOD_DESCRIPTION, $updatedShippingMethod->getDescription(), 'description shall be not updatable by app but has been updated.');
        static::assertSame(self::DELIVERY_TIME_ID_INITIAL, $updatedShippingMethod->getDeliveryTimeId(), 'deliveryTime shall be not updatable by app but has been updated.');
        static::assertSame(self::POSITION_INITIAL, $updatedShippingMethod->getPosition(), 'position shall be not updatable by app but has been updated.');
        static::assertSame(self::TRACKING_URL_UPDATED, $updatedShippingMethod->getTrackingUrl(), 'tracking-url shall be updatable by app but hasn\'t been updated.');
        static::assertSame($mediaId, $updatedShippingMethod->getMediaId(), 'mediaId shall be not updatable by app but has been updated.');

        $this->removeApp(self::APP_PATH);
    }

    public function testUpdateShippingMethodsChangeIdentifierShallAddNewMethodAndDeactivateOldOne(): void
    {
        $this->installApp(self::APP_PATH);

        $expectedShippingMethodCountBeforeUpdate = 4;
        $expectedAppShippingMethodCountBeforeUpdate = 2;
        $expectedShippingMethodCountAfterUpdate = 5;
        $expectedAppShippingMethodCountAfterUpdate = 3;

        $numberOfShippingMethods = $this->getNumberOfShippingMethods();
        static::assertSame($expectedShippingMethodCountBeforeUpdate, $numberOfShippingMethods, 'Number of expected shipping methods before update does not match');
        $numberOfAppShippingMethods = $this->getNumberOfShippingMethods(true);
        static::assertSame($expectedAppShippingMethodCountBeforeUpdate, $numberOfAppShippingMethods, 'Number of expected app shipping methods before update does not match');

        $this->updateApp($this->getAppId(), self::MANIFEST_UPDATE_IDENTIFIER);

        $numberOfShippingMethodsAfterUpdate = $this->getNumberOfShippingMethods();
        static::assertSame($expectedShippingMethodCountAfterUpdate, $numberOfShippingMethodsAfterUpdate, 'Number of expected shipping methods after update does not match');

        $numberOfAppShippingMethodsAfterUpdate = $this->getNumberOfShippingMethods(true);
        static::assertSame($expectedAppShippingMethodCountAfterUpdate, $numberOfAppShippingMethodsAfterUpdate, 'Number of expected app shipping methods after update does not match');

        $updatedApp = $this->getApp();
        $appShippingMethods = $updatedApp->getAppShippingMethods();
        static::assertInstanceOf(EntityCollection::class, $appShippingMethods);
        static::assertCount($expectedAppShippingMethodCountAfterUpdate, $appShippingMethods);

        $shippingMethod = $appShippingMethods->filterByProperty('identifier', self::IDENTIFIER_SECOND_METHOD_UPDATED)->first();
        static::assertInstanceOf(AppShippingMethodEntity::class, $shippingMethod);

        $deactivatedAppShippingMethod = $appShippingMethods->filterByProperty('identifier', self::IDENTIFIER_SECOND_METHOD)->first();
        static::assertInstanceOf(AppShippingMethodEntity::class, $deactivatedAppShippingMethod);

        $deactivatedShippingMethod = $deactivatedAppShippingMethod->getShippingMethod();
        static::assertInstanceOf(ShippingMethodEntity::class, $deactivatedShippingMethod);
        static::assertFalse($deactivatedShippingMethod->getActive());

        $this->removeApp(self::APP_PATH);
    }

    public function testUpdateShippingMethodsShouldNotActivatedAndDeactivatedShippingMethodDuringUpdate(): void
    {
        $this->installApp(self::ACTIVE_FLAG_APP_PATH);

        $appShippingMethods = $this->getApp()->getAppShippingMethods();
        static::assertInstanceOf(EntityCollection::class, $appShippingMethods);

        $firstShippingMethod = $appShippingMethods->filterByProperty('identifier', 'swagFirstShippingMethod')->first()?->getShippingMethod();
        $secondShippingMethod = $appShippingMethods->filterByProperty('identifier', self::IDENTIFIER_SECOND_METHOD)->first()?->getShippingMethod();
        static::assertInstanceOf(ShippingMethodEntity::class, $firstShippingMethod);
        static::assertInstanceOf(ShippingMethodEntity::class, $secondShippingMethod);
        static::assertTrue($firstShippingMethod->getActive(), 'Install: swagFirstShippingMethod is not activated');
        static::assertFalse($secondShippingMethod->getActive(), 'Install: swagSecondShippingMethod is not deactivated');

        $this->updateApp($this->getAppId(), __DIR__ . '/../_fixtures/ShippingMethodActiveFlagUpdate/test/manifest.xml');

        $updatedAppShippingMethods = $this->getApp()->getAppShippingMethods();
        static::assertInstanceOf(EntityCollection::class, $updatedAppShippingMethods);

        $updatedFirstShippingMethod = $updatedAppShippingMethods->filterByProperty('identifier', 'swagFirstShippingMethod')->first()?->getShippingMethod();
        $updatedSecondShippingMethod = $updatedAppShippingMethods->filterByProperty('identifier', self::IDENTIFIER_SECOND_METHOD)->first()?->getShippingMethod();
        static::assertInstanceOf(ShippingMethodEntity::class, $updatedFirstShippingMethod);
        static::assertInstanceOf(ShippingMethodEntity::class, $updatedSecondShippingMethod);
        static::assertTrue($updatedFirstShippingMethod->getActive(), 'Update: swagFirstShippingMethod is activated');
        static::assertFalse($updatedSecondShippingMethod->getActive(), 'Update: swagSecondShippingMethod is deactivated');

        $this->removeApp(self::ACTIVE_FLAG_APP_PATH);
    }

    private function getNumberOfShippingMethods(bool $getAppShippingMethod = false): int
    {
        $sql = 'SELECT count(*) from `shipping_method`';

        if ($getAppShippingMethod) {
            $sql = 'SELECT count(*) from `app_shipping_method`';
        }

        return (int) $this->connection->fetchOne($sql);
    }

    private function getApp(): AppEntity
    {
        $appRepository = $this->getContainer()->get('app.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('appShippingMethods.shippingMethod');
        $criteria->addFilter(new EqualsFilter('app.name', 'test'));

        $app = $appRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(AppEntity::class, $app);

        return $app;
    }

    private function updateApp(string $appId, string $manifestXml): void
    {
        $manifest = Manifest::createFromXmlFile($manifestXml);

        $shippingMethodPersister = new ShippingMethodPersister(
            $this->getContainer()->get('shipping_method.repository'),
            $this->getContainer()->get('app_shipping_method.repository'),
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get(AppLoader::class),
        );

        $shippingMethodPersister->updateShippingMethods($manifest, $appId, Defaults::LANGUAGE_SYSTEM, Context::createDefaultContext());
    }

    private function getAppId(): string
    {
        $sql = 'SELECT `id` from `app` where name ="' . self::APP_NAME . '";';
        $appUuid = $this->connection->fetchOne($sql);
        if (!$appUuid) {
            static::fail('Cannot find appId for app with name ' . self::APP_NAME);
        }

        return Uuid::fromBytesToHex($appUuid);
    }
}
