<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Lifecycle\Update;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Update\AbstractAppUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SystemConfigTestBehaviour;
use Symfony\Component\Filesystem\Filesystem;

class DefaultAppUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;
    use ExtensionBehaviour;
    use SystemConfigTestBehaviour;

    private AbstractAppUpdater $updater;

    private EntityRepositoryInterface $appRepo;

    private Context $context;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);
        $this->context = Context::createDefaultContext();
        $this->updater = $this->getContainer()->get(AbstractAppUpdater::class);
        $this->appRepo = $this->getContainer()->get('app.repository');
        //simulate that a user was logged in
        $this->createAdminStoreContext();
    }

    public function testItUpdatesApps(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/SwagApp');
        $this->setLicenseDomain('not_null');

        $this->getRequestHandler()->append(new Response(200, [], '{}'));
        $this->getRequestHandler()->append(new Response(200, [], file_get_contents(__DIR__ . '/../_fixtures/responses/my-licenses.json')));
        $this->getRequestHandler()->append(new Response(200, [], '{"location": "http://localhost/my.zip", "type": "app"}'));
        $this->getRequestHandler()->append(new Response(200, [], file_get_contents(__DIR__ . '/../_fixtures/store_zips/swagApp2.zip')));
        $expectedLocation = $this->getContainer()->getParameter('kernel.app_dir') . '/SwagApp';

        try {
            $this->updater->updateApps($this->context);

            $apps = $this->appRepo->search(new Criteria(), $this->context);

            static::assertEquals(1, $apps->count());
            /** @var AppEntity $testApp */
            $testApp = $apps->first();
            static::assertEquals('2.0.0', $testApp->getVersion());
        } finally {
            (new Filesystem())->remove($expectedLocation);
        }
    }

    public function testItDoesNotUpdateNewPermissions(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/SwagApp');
        $this->setLicenseDomain('not_null');

        $this->getRequestHandler()->append(new Response(200, [], file_get_contents(__DIR__ . '/../_fixtures/responses/my-licenses.json')));
        $this->getRequestHandler()->append(new Response(200, [], '{"location": "http://localhost/my.zip", "type": "app"}'));
        $this->getRequestHandler()->append(new Response(200, [], file_get_contents(__DIR__ . '/../_fixtures/store_zips/swagApp2_new_permission.zip')));
        $expectedLocation = $this->getContainer()->getParameter('kernel.app_dir') . '/SwagApp';

        try {
            $this->updater->updateApps($this->context);

            $apps = $this->appRepo->search(new Criteria(), $this->context);

            static::assertEquals(1, $apps->count());
            /** @var AppEntity $testApp */
            $testApp = $apps->first();
            static::assertEquals('1.0.0', $testApp->getVersion());
        } finally {
            (new Filesystem())->remove($expectedLocation);
        }
    }
}
