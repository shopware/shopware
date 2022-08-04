<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Delta;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Delta\DomainsDeltaProvider;
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
class DomainsDeltaProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetName(): void
    {
        $expected = 'domains';
        static::assertSame($expected, DomainsDeltaProvider::DELTA_NAME);
        static::assertSame($expected, (new DomainsDeltaProvider())->getDeltaName());
    }

    public function testGetDomainsDelta(): void
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

        // Modify the existing privileges to get a delta
        $app->setAllowedHosts([]);

        $delta = (new DomainsDeltaProvider())->getReport($manifest, $app);

        static::assertCount(6, $delta);
        static::assertEquals([
            'my.app.com',
            'test.com',
            'base-url.com',
            'main-module',
            'swag-test.com',
            'payment.app',
        ], $delta);
    }

    public function testHasDelta(): void
    {
        $context = Context::createDefaultContext();
        $manifest = $this->getTestManifest();

        $this->getAppLifecycle()->install($manifest, false, $context);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('name', 'test'));

        /** @var AppEntity $app */
        $app = $this->getAppRepository()
            ->search($criteria, $context)
            ->first();

        static::assertFalse((new DomainsDeltaProvider())->hasDelta($manifest, $app));

        $app->setAllowedHosts([]);

        static::assertTrue((new DomainsDeltaProvider())->hasDelta($manifest, $app));
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
