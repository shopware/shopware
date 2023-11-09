<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Update;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Lifecycle\Update\AbstractAppUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
class DefaultAppUpdaterTest extends TestCase
{
    use ExtensionBehaviour;
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private AbstractAppUpdater $updater;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepo;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->updater = $this->getContainer()->get(AbstractAppUpdater::class);
        $this->appRepo = $this->getContainer()->get('app.repository');
        // simulate that a user was logged in
        $this->createAdminStoreContext();
    }

    public function testItUpdatesApps(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/SwagApp');
        $this->setLicenseDomain('not_null');

        $licensesJson = \file_get_contents(__DIR__ . '/../_fixtures/responses/my-licenses.json');
        $swagAppJson = \file_get_contents(__DIR__ . '/../_fixtures/store_zips/swagApp2.zip');

        static::assertNotFalse($licensesJson);
        static::assertNotFalse($swagAppJson);

        $this->getStoreRequestHandler()->append(new Response(200, [], '{}'));
        $this->getStoreRequestHandler()->append(new Response(200, [], $licensesJson));
        $this->getStoreRequestHandler()->append(new Response(200, [], '{"location": "http://localhost/my.zip", "type": "app"}'));
        $this->getStoreRequestHandler()->append(new Response(200, [], $swagAppJson));
        $expectedLocation = $this->getContainer()->getParameter('kernel.app_dir') . '/SwagApp';

        try {
            $this->updater->updateApps($this->context);

            $apps = $this->appRepo->search(new Criteria(), $this->context)->getEntities();

            static::assertCount(1, $apps);
            $testApp = $apps->first();
            static::assertNotNull($testApp);
            static::assertSame('2.0.0', $testApp->getVersion());
        } finally {
            (new Filesystem())->remove($expectedLocation);
        }
    }

    public function testItDoesNotUpdateNewPermissions(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/SwagApp');
        $this->setLicenseDomain('not_null');

        $licensesJson = \file_get_contents(__DIR__ . '/../_fixtures/responses/my-licenses.json');
        $swagAppJson = \file_get_contents(__DIR__ . '/../_fixtures/store_zips/swagApp2_new_permission.zip');

        static::assertNotFalse($licensesJson);
        static::assertNotFalse($swagAppJson);

        $this->getStoreRequestHandler()->append(new Response(200, [], $licensesJson));
        $this->getStoreRequestHandler()->append(new Response(200, [], '{"location": "http://localhost/my.zip", "type": "app"}'));
        $this->getStoreRequestHandler()->append(new Response(200, [], $swagAppJson));
        $expectedLocation = $this->getContainer()->getParameter('kernel.app_dir') . '/SwagApp';

        try {
            $this->updater->updateApps($this->context);

            $apps = $this->appRepo->search(new Criteria(), $this->context)->getEntities();

            static::assertCount(1, $apps);
            $testApp = $apps->first();
            static::assertNotNull($testApp);
            static::assertSame('1.0.0', $testApp->getVersion());
        } finally {
            (new Filesystem())->remove($expectedLocation);
        }
    }
}
