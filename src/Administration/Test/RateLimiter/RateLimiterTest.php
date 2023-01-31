<?php declare(strict_types=1);

namespace Shopware\Administration\Test\RateLimiter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Customer\SalesChannel\CustomerTestTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;

/**
 * @internal
 * @group slow
 */
class RateLimiterTest extends TestCase
{
    use CustomerTestTrait;
    use AdminApiTestBehaviour;

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

    public function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->appRepository = $this->getContainer()->get('app.repository');
    }

    public function tearDown(): void
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
