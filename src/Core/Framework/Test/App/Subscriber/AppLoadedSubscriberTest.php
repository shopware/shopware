<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Subscriber\AppLoadedSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class AppLoadedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetSubscribedEvents(): void
    {
        static::assertEquals([
            'app.loaded' => 'unserialize',
        ], AppLoadedSubscriber::getSubscribedEvents());
    }

    public function testUnserialize(): void
    {
        /** @var EntityRepository $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');

        $id = Uuid::randomHex();

        $appRepository->create([
            [
                'id' => $id,
                'name' => 'App',
                'path' => __DIR__ . '/../Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test App',
                'accessToken' => 'test',
                'iconRaw' => file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png'),
                'integration' => [
                    'label' => 'App1',
                    'writeAccess' => false,
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'App1',
                ],
            ],
        ], Context::createDefaultContext());

        /** @var AppEntity $app */
        $app = $appRepository->search(new Criteria([$id]), Context::createDefaultContext())->get($id);
        static::assertNotNull($app);
        static::assertEquals(
            base64_encode((string) file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png')),
            $app->getIcon()
        );
    }
}
