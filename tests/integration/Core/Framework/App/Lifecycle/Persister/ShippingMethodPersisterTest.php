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
    private const MANIFEST_UPDATE_NAME = __DIR__ . '/../_fixtures/shippingMethodUpdateName/test/manifest.xml';
    private const MANIFEST_UPDATE_IDENTIFIER = __DIR__ . '/../_fixtures/shippingMethodUpdateIdentifier/test/manifest.xml';
    private const IDENTIFIER = 'swagSecondShippingMethod';
    private const IDENTIFIER_UPDATED = 'swagUpdatedShippingMethod';
    private const METHOD_NAME = 'updated Shipping Method Name';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
    }

    public function testUpdateShippingMethodsChangeNameShallOnlyChangeName(): void
    {
        $this->installApp(self::APP_PATH);

        $sql = 'SELECT count(*) from `shipping_method`';
        $numberOfShippingMethods = $this->connection->fetchOne($sql);
        $sql = 'SELECT count(*) from `app_shipping_method`';
        $numberOfAppShippingMethods = $this->connection->fetchOne($sql);

        $sql = 'SELECT id from `app` where name ="' . self::APP_NAME . '";';
        $appUuid = $this->connection->fetchOne($sql);
        $appId = Uuid::fromBytesToHex($appUuid);

        $this->updateApp($appId, self::MANIFEST_UPDATE_NAME);

        $sql = 'SELECT count(*) from `shipping_method`';
        $numberOfShippingMethodsAfterUpdate = $this->connection->fetchOne($sql);
        static::assertSame($numberOfShippingMethods, $numberOfShippingMethodsAfterUpdate);

        $sql = 'SELECT count(*) from `app_shipping_method`';
        $numberOfAppShippingMethodsAfterUpdate = $this->connection->fetchOne($sql);
        static::assertSame($numberOfAppShippingMethods, $numberOfAppShippingMethodsAfterUpdate);

        $updatedApp = $this->getApp();
        $appShippingMethods = $updatedApp->getAppShippingMethods();
        static::assertInstanceOf(EntityCollection::class, $appShippingMethods);
        static::assertCount(2, $appShippingMethods);

        $updatedAppShippingMethod = $appShippingMethods->filterByProperty('identifier', self::IDENTIFIER)->first();
        static::assertInstanceOf(AppShippingMethodEntity::class, $updatedAppShippingMethod);
        $shippingMethod = $updatedAppShippingMethod->getShippingMethod();
        static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
        static::assertSame(self::METHOD_NAME, $shippingMethod->getName());

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

        $sql = 'SELECT `id` from `app` where name ="' . self::APP_NAME . '";';
        $appUuid = $this->connection->fetchOne($sql);
        $appId = Uuid::fromBytesToHex($appUuid);

        $this->updateApp($appId, self::MANIFEST_UPDATE_IDENTIFIER);

        $numberOfShippingMethodsAfterUpdate = $this->getNumberOfShippingMethods();
        static::assertSame($expectedShippingMethodCountAfterUpdate, $numberOfShippingMethodsAfterUpdate, 'Number of expected shipping methods after update does not match');

        $numberOfAppShippingMethodsAfterUpdate = $this->getNumberOfShippingMethods(true);
        static::assertSame($expectedAppShippingMethodCountAfterUpdate, $numberOfAppShippingMethodsAfterUpdate, 'Number of expected app shipping methods after update does not match');

        $updatedApp = $this->getApp();
        $appShippingMethods = $updatedApp->getAppShippingMethods();
        static::assertInstanceOf(EntityCollection::class, $appShippingMethods);
        static::assertCount($expectedAppShippingMethodCountAfterUpdate, $appShippingMethods);

        $shippingMethod = $appShippingMethods->filterByProperty('identifier', self::IDENTIFIER_UPDATED)->first();
        static::assertInstanceOf(AppShippingMethodEntity::class, $shippingMethod);

        $deactivatedAppShippingMethod = $appShippingMethods->filterByProperty('identifier', self::IDENTIFIER)->first();
        static::assertInstanceOf(AppShippingMethodEntity::class, $deactivatedAppShippingMethod);

        $deactivatedShippingMethod = $deactivatedAppShippingMethod->getShippingMethod();
        static::assertInstanceOf(ShippingMethodEntity::class, $deactivatedShippingMethod);
        static::assertFalse($deactivatedShippingMethod->getActive());

        $this->removeApp(self::APP_PATH);
    }

    private function getNumberOfShippingMethods(?bool $getAppShippingMethod = false): int
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
            $this->getContainer()->get('rule.repository'),
            $this->getContainer()->get('delivery_time.repository'),
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get(AppLoader::class),
        );

        $shippingMethodPersister->updateShippingMethods($manifest, $appId, Defaults::LANGUAGE_SYSTEM, Context::createDefaultContext());
    }
}
