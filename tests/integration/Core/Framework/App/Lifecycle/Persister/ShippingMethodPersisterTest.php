<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Rule\RuleEntity;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

/**
 * @internal
 */
class ShippingMethodPersisterTest extends TestCase
{
    use ExtensionBehaviour;
    use IntegrationTestBehaviour;

    private const APP_NAME = 'test';
    private const APP_PATH = __DIR__ . '/../_fixtures/shippingMethodBase/test';
    private const MANIFEST_UPDATE = __DIR__ . '/../_fixtures/shippingMethodUpdate/test/manifest.xml';
    private const MANIFEST_UPDATE_IDENTIFIER = __DIR__ . '/../_fixtures/shippingMethodUpdateIdentifier/test/manifest.xml';
    private const IDENTIFIER_FIRST_METHOD = 'swagFirstShippingMethod';
    private const IDENTIFIER_SECOND_METHOD = 'swagSecondShippingMethod';
    private const IDENTIFIER_SECOND_METHOD_UPDATED = 'swagUpdatedShippingMethod';
    private const INITIAL_METHOD_NAME = 'first Shipping Method';
    private const INITIAL_METHOD_DESCRIPTION = 'This is a simple description';

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

        $app = $this->getApp();
        $appShippingMethods = $app->getAppShippingMethods();
        static::assertInstanceOf(EntityCollection::class, $appShippingMethods);
        $appShippingMethod = $appShippingMethods->filterByProperty('identifier', self::IDENTIFIER_FIRST_METHOD)->first();
        static::assertInstanceOf(AppShippingMethodEntity::class, $appShippingMethod);
        $shippingMethod = $appShippingMethod->getShippingMethod();
        static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
        $mediaId = $shippingMethod->getMediaId();

        $updatedDeliveryTimeId = $this->updateDeliveryTime($shippingMethod);
        $updatedAvailabilityRuleId = $this->updateAvailabilityRule($shippingMethod);

        $this->updateApp($this->getAppId(), self::MANIFEST_UPDATE);

        static::assertSame(self::INITIAL_METHOD_NAME, $shippingMethod->getName());
        static::assertSame(self::INITIAL_METHOD_DESCRIPTION, $shippingMethod->getDescription());
        static::assertSame($mediaId, $shippingMethod->getMediaId());
        static::assertSame($updatedAvailabilityRuleId, $shippingMethod->getAvailabilityRuleId());
        static::assertSame($updatedDeliveryTimeId, $shippingMethod->getDeliveryTimeId());

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

    private function getAppId(): string
    {
        $sql = 'SELECT id from `app` where name ="' . self::APP_NAME . '";';
        $appUuid = $this->connection->fetchOne($sql);

        return Uuid::fromBytesToHex($appUuid);
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

    private function updateDeliveryTime(ShippingMethodEntity $shippingMethod): string
    {
        $deliveryTimeRepository = $this->getContainer()->get('delivery_time.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('id', $shippingMethod->getDeliveryTimeId())]));
        $criteria->setLimit(1);
        $deliveryTime = $deliveryTimeRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(DeliveryTimeEntity::class, $deliveryTime);

        $deliveryTimeId = $deliveryTime->getId();
        $shippingMethod->setDeliveryTimeId($deliveryTimeId);
        static::assertSame($deliveryTimeId, $shippingMethod->getDeliveryTimeId());

        return $deliveryTimeId;
    }

    private function updateAvailabilityRule(ShippingMethodEntity $shippingMethod): string
    {
        $ruleRepository = $this->getContainer()->get('rule.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('invalid', 0));
        $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('id', $shippingMethod->getAvailabilityRuleId())]));
        $criteria->setLimit(1);
        $rule = $ruleRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(RuleEntity::class, $rule);

        $ruleId = $rule->getId();
        $shippingMethod->setAvailabilityRuleId($ruleId);
        static::assertSame($ruleId, $shippingMethod->getAvailabilityRuleId());

        return $ruleId;
    }
}
