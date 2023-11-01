<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\RateLimiter;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Tests\Integration\Core\Checkout\Customer\SalesChannel\CustomerTestTrait;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @internal
 */
#[Group('slow')]
class RateLimiterTest extends TestCase
{
    use AdminApiTestBehaviour;
    use CustomerTestTrait;

    private Context $context;

    private EntityRepository $appRepository;

    public static function setUpBeforeClass(): void
    {
        DisableRateLimiterCompilerPass::disableNoLimit();
    }

    public static function tearDownAfterClass(): void
    {
        DisableRateLimiterCompilerPass::enableNoLimit();
    }

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->appRepository = $this->getContainer()->get('app.repository');
    }

    protected function tearDown(): void
    {
        DisableRateLimiterCompilerPass::enableNoLimit();
    }

    public function testRateLimitNotificationRoute(): void
    {
        $ids = new IdsCollection();
        $integrationId = $ids->create('integration');
        $client = $this->getBrowserAuthenticatedWithIntegration($integrationId);

        $this->createApp($integrationId);
        $url = '/api/notification';
        $data = [
            'status' => 'success',
            'message' => 'This is a notification',
        ];

        for ($i = 0; $i <= 10; ++$i) {
            $client->request('POST', $url, [], [], [], (string) json_encode($data));

            $response = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            if ($i >= 10) {
                static::assertArrayHasKey('errors', $response);
                static::assertEquals(429, $response['errors'][0]['status']);
                static::assertEquals('FRAMEWORK__NOTIFICATION_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertEquals(200, $client->getResponse()->getStatusCode());
            }
        }
    }

    public function testRateLimitTheSameTwice(): void
    {
        $rateLimiter = new RateLimiter();
        $rateLimiter->registerLimiterFactory('test', new RateLimiterFactory(
            [
                'enabled' => true,
                'id' => 'test_limiter',
                'policy' => 'sliding_window',
                'limit' => 1,
                'interval' => '15 seconds'
            ],
            $this->createMock(StorageInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(LockFactory::class),
        ));
        $key = '5655e0ce-8238-407f-ae2e-d65c01b7dea3';
        $rateLimiter->ensureAccepted('test', $key);
        $rateLimiter->reset('test', $key);
        $rateLimiter->ensureAccepted('test', $key);
        $rateLimiter->reset('test', $key);
    }

    private function createApp(string $integrationId): void
    {
        $payload = [
            'name' => 'TestNotification',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'Test notification',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'mainModule' => [
                'source' => 'http://main-module-1',
            ],
            'integrationId' => $integrationId,
            'aclRole' => [
                'name' => 'TestNotification',
                'privileges' => [
                    'notification:create',
                ],
            ],
        ];

        $this->appRepository->create([$payload], $this->context);
    }
}
