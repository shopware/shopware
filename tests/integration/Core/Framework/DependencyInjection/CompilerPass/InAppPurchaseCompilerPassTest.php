<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\InAppPurchaseCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InAppPurchaseCompilerPass::class)]
class InAppPurchaseCompilerPassTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        InAppPurchase::reset();

        $this->setUpExtensions();
    }

    public function testCompilerPass(): void
    {
        $expiresAt = (new \DateTime())->add(new \DateInterval('P1D'));

        $inAppRepo = $this->getContainer()->get('in_app_purchase.repository');
        $inAppRepo->create([
            [
                'id' => $this->ids->get('in-app-1'),
                'identifier' => 'active-app-feature',
                'appId' => $this->ids->get('app'),
                'expiresAt' => $expiresAt,
                'active' => true,
            ],
            [
                'id' => $this->ids->get('in-app-2'),
                'identifier' => 'inactive-app-feature',
                'appId' => $this->ids->get('app'),
                'expiresAt' => $expiresAt,
                'active' => false,
            ],
            [
                'id' => $this->ids->get('in-app-3'),
                'identifier' => 'active-plugin-feature',
                'pluginId' => $this->ids->get('plugin'),
                'expiresAt' => $expiresAt,
                'active' => true,
            ],
            [
                'id' => $this->ids->get('in-app-4'),
                'identifier' => 'inactive-plugin-feature',
                'pluginId' => $this->ids->get('plugin'),
                'expiresAt' => $expiresAt,
                'active' => false,
            ],
        ], Context::createDefaultContext());

        $this->compile();

        static::assertTrue(InAppPurchase::isActive('active-app-feature'));
        static::assertFalse(InAppPurchase::isActive('inactive-app-feature'));
        static::assertTrue(InAppPurchase::isActive('active-plugin-feature'));
        static::assertFalse(InAppPurchase::isActive('inactive-plugin-feature'));

        static::assertSame(['active-app-feature', 'active-plugin-feature'], InAppPurchase::all());
        static::assertSame(['active-app-feature'], InAppPurchase::getByExtension($this->ids->get('app')));
        static::assertSame(['active-plugin-feature'], InAppPurchase::getByExtension($this->ids->get('plugin')));
    }

    private function compile(): void
    {
        $container = new ContainerBuilder();
        $container->set(Connection::class, $this->getContainer()->get(Connection::class));
        $container->addCompilerPass(new InAppPurchaseCompilerPass());
        $container->compile();
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
