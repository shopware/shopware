<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Delta;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Delta\PermissionsDeltaProvider;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class PermissionsDeltaProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetName(): void
    {
        $expected = 'permissions';
        static::assertSame($expected, PermissionsDeltaProvider::DELTA_NAME);
        static::assertSame($expected, (new PermissionsDeltaProvider())->getDeltaName());
    }

    public function testGetPermissionsDelta(): void
    {
        $context = Context::createDefaultContext();
        $manifest = $this->getTestManifest();

        $this->getAppLifecycle()->install($manifest, false, $context);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('name', 'test'))
            ->addAssociation('acl_role');

        /** @var AppEntity $app */
        $app = $this->getAppRepository()
            ->search($criteria, $context)
            ->first();

        // Modify the existing privileges to get a diff
        $app->getAclRole()->setPrivileges(['customer:read']);

        $diff = (new PermissionsDeltaProvider())->getReport($manifest, $app);

        static::assertCount(6, $diff);
        static::assertArrayHasKey('category', $diff);
        static::assertArrayHasKey('custom_fields', $diff);
        static::assertArrayHasKey('order', $diff);
        static::assertArrayHasKey('product', $diff);
        static::assertArrayHasKey('settings', $diff);
        static::assertArrayHasKey('additional_privileges', $diff);
    }

    public function testHasDelta(): void
    {
        $context = Context::createDefaultContext();
        $manifest = $this->getTestManifest();

        $this->getAppLifecycle()->install($manifest, false, $context);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('name', 'test'))
            ->addAssociation('acl_role');

        /** @var AppEntity $app */
        $app = $this->getAppRepository()
            ->search($criteria, $context)
            ->first();

        $hasDelta = (new PermissionsDeltaProvider())->hasDelta($manifest, $app);

        static::assertFalse($hasDelta);
    }

    private function getAppLifecycle(): AppLifecycle
    {
        return $this->getContainer()->get(AppLifecycle::class);
    }

    private function getAppRepository(): EntityRepository
    {
        return $this->getContainer()->get('app.repository');
    }

    private function getTestManifest(): Manifest
    {
        return Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
    }
}
