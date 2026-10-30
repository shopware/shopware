<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Api\AppSystemHealthController;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\SystemCheck\AppHealth;
use Shopware\Core\Framework\App\SystemCheck\AppSystemCheck;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppSystemHealthController::class)]
class AppSystemHealthControllerTest extends TestCase
{
    private const OK_APP_ID = '0190a1b2c3d47e8f9a0b1c2d3e4f5a6b';

    private const WARNING_APP_ID = '0190a1b2c3d47e8f9a0b1c2d3e4f5a6c';

    public function testReturnsAllAppsWithWorstStatus(): void
    {
        $response = $this->controllerFor($this->twoApps())->health(new Request());

        $body = $this->decode($response->getContent());
        static::assertSame('WARNING', $body['status']);
        static::assertSame([self::OK_APP_ID, self::WARNING_APP_ID], array_keys($body['apps']));
        static::assertSame('delivery_failing', $body['apps'][self::WARNING_APP_ID]['reason']);
    }

    public function testFiltersByAppIdAndNormalisesUppercaseInput(): void
    {
        $response = $this->controllerFor($this->twoApps())
            ->health(new Request(['appId' => strtoupper(self::OK_APP_ID)]));

        $body = $this->decode($response->getContent());
        static::assertSame([self::OK_APP_ID], array_keys($body['apps']));
        static::assertSame('OK', $body['status']);
    }

    public function testUnknownAppIdIsNotFound(): void
    {
        $unknown = '0190a1b2c3d47e8f9a0b1c2d3e4f5aff';

        $this->expectExceptionObject(AppException::notFoundByField($unknown, 'id'));

        $this->controllerFor($this->twoApps())->health(new Request(['appId' => $unknown]));
    }

    public function testInvalidAppIdIsABadRequest(): void
    {
        $this->expectExceptionObject(RoutingException::invalidRequestParameter('appId'));

        $this->controllerFor($this->twoApps())->health(new Request(['appId' => 'not-a-uuid']));
    }

    public function testNoAppsSerialisesAsEmptyObject(): void
    {
        $response = $this->controllerFor([])->health(new Request());

        static::assertSame('{"status":"OK","apps":{}}', $response->getContent());
    }

    /**
     * @param array<string, AppHealth> $apps
     */
    private function controllerFor(array $apps): AppSystemHealthController
    {
        $check = $this->createStub(AppSystemCheck::class);
        $check->method('collect')->willReturn($apps);

        return new AppSystemHealthController($check);
    }

    /**
     * @return array<string, AppHealth>
     */
    private function twoApps(): array
    {
        return [
            self::OK_APP_ID => new AppHealth('OkApp', Status::OK),
            self::WARNING_APP_ID => new AppHealth(
                name: 'FailingApp',
                status: Status::WARNING,
                reason: AppSystemCheck::REASON_DELIVERY_FAILING,
                lastStatusCode: 500,
                lastAttemptAt: '2026-06-15 12:00:00.000',
            ),
        ];
    }

    /**
     * @return array{status: string, apps: array<string, array<string, mixed>>}
     */
    private function decode(string|false $content): array
    {
        static::assertIsString($content);

        /** @var array{status: string, apps: array<string, array<string, mixed>>} $decoded */
        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
