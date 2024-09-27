<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Store\InAppPurchaseRegistry;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InAppPurchaseRegistry::class)]
class InAppPurchaseRegistryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->setUpExtensions();
    }

    public function testRegister(): void
    {
        $expiresAt = (new \DateTime())->add(new \DateInterval('P1D'));

        $inAppRepo = $this->getContainer()->get('in_app_purchase.repository');
        $inAppRepo->create([
            [
                'id' => $this->ids->get('in-app-1'),
                'identifier' => 'Test app active-active-app-feature',
                'appId' => $this->ids->get('app'),
                'expiresAt' => $expiresAt,
                'active' => true,
            ],
            [
                'id' => $this->ids->get('in-app-2'),
                'identifier' => 'Test app active-inactive-app-feature',
                'appId' => $this->ids->get('app'),
                'expiresAt' => $expiresAt,
                'active' => false,
            ],
            [
                'id' => $this->ids->get('in-app-3'),
                'identifier' => 'Test plugin active-active-plugin-feature',
                'pluginId' => $this->ids->get('plugin'),
                'expiresAt' => $expiresAt,
                'active' => true,
            ],
            [
                'id' => $this->ids->get('in-app-4'),
                'identifier' => 'Test plugin active-inactive-plugin-feature',
                'pluginId' => $this->ids->get('plugin'),
                'expiresAt' => $expiresAt,
                'active' => false,
            ],
        ], Context::createDefaultContext());

        $registry = new InAppPurchaseRegistry($this->getContainer()->get(Connection::class));
        $registry->register();

        static::assertTrue(InAppPurchase::isActive('Test app active-active-app-feature'));
        static::assertFalse(InAppPurchase::isActive('Test app active-inactive-app-feature'));
        static::assertTrue(InAppPurchase::isActive('Test plugin active-active-plugin-feature'));
        static::assertFalse(InAppPurchase::isActive('Test plugin active-inactive-plugin-feature'));

        static::assertSame(['Test app active-active-app-feature', 'Test plugin active-active-plugin-feature'], InAppPurchase::all());
        static::assertSame(['Test app active-active-app-feature'], InAppPurchase::getByExtension($this->ids->get('app')));
        static::assertSame(['Test plugin active-active-plugin-feature'], InAppPurchase::getByExtension($this->ids->get('plugin')));
    }

    public function testRegisterWithOutdatedData(): void
    {
        $expiresAt = (new \DateTime())->add(new \DateInterval('P1D'));

        $inAppRepo = $this->getContainer()->get('in_app_purchase.repository');

        $inAppRepo->upsert([
            [
                'id' => $this->ids->get('in-app-1'),
                'identifier' => 'Test app active-active-app-feature',
                'appId' => $this->ids->get('app'),
                'expiresAt' => $expiresAt,
                'active' => true,
            ],
            [
                'id' => $this->ids->get('in-app-2'),
                'identifier' => 'Test app active-inactive-app-feature',
                'appId' => $this->ids->get('app'),
                'expiresAt' => $expiresAt,
                'active' => false,
            ],
            [
                'id' => $this->ids->get('in-app-3'),
                'identifier' => 'Test app active-active-but-expired-app-feature',
                'appId' => $this->ids->get('app'),
                'expiresAt' => new \DateTime('2019-01-01'),
                'active' => true,
            ],
            [
                'id' => $this->ids->get('in-app-4'),
                'identifier' => 'Test app active-inactive-and-expired-app-feature',
                'appId' => $this->ids->get('app'),
                'expiresAt' => new \DateTime('2019-01-01'),
                'active' => false,
            ],
        ], Context::createDefaultContext());

        $registry = new InAppPurchaseRegistry($this->getContainer()->get(Connection::class));
        $registry->register();

        static::assertTrue(InAppPurchase::isActive('Test app active-active-app-feature'));
        static::assertFalse(InAppPurchase::isActive('Test app active-inactive-app-feature'));
        static::assertFalse(InAppPurchase::isActive('Test app active-active-but-expired-app-feature'));
        static::assertFalse(InAppPurchase::isActive('Test app active-inactive-and-expired-app-feature'));

        static::assertSame(['Test app active-active-app-feature'], InAppPurchase::all());
        static::assertSame(['Test app active-active-app-feature'], InAppPurchase::getByExtension($this->ids->get('app')));
    }

    private function setUpExtensions(): void
    {
        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([
            [
                'id' => $this->ids->get('app'),
                'name' => 'Test app active',
                'path' => __DIR__ . '/_fixtures/test-app',
                'version' => '1.0.0',
                'active' => true,
                'integration' => [
                    'id' => $this->ids->get('integration'),
                    'label' => 'Test integration',
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'label' => 'Test app active',
                'aclRole' => [
                    'id' => $this->ids->get('acl-role'),
                    'name' => 'Test role',
                    'privileges' => [
                        'app.system_config',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $pluginRepository = $this->getContainer()->get('plugin.repository');
        $pluginRepository->create([
            [
                'id' => $this->ids->get('plugin'),
                'name' => 'Test plugin active',
                'label' => 'Test plugin active',
                'version' => '1.0.0',
                'baseClass' => 'TestPluginActive',
                'active' => true,
                'autoload' => [],
            ],
        ], Context::createDefaultContext());
    }
}
