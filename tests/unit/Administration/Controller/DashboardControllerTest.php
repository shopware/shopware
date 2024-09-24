<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\DashboardController;
use Shopware\Administration\Dashboard\OrderAmountService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(DashboardController::class)]
class DashboardControllerTest extends TestCase
{
    #[DataProvider('requestProvider')]
    public function testRequest(Request $request, string $since, bool $paid, string $timezone): void
    {
        $service = $this->createMock(OrderAmountService::class);

        $context = Context::createDefaultContext();

        $service->expects(static::once())
            ->method('load')
            ->with($context, $since, $paid, $timezone)
            ->willReturn([
                [
                    'date' => '2021-01-01',
                    'count' => 100,
                    'amount' => 1300.3,
                ],
                [
                    'date' => '2021-01-02',
                    'count' => 200,
                    'amount' => 2600.1,
                ],
            ]);

        $controller = new DashboardController($service);
        $response = $controller->orderAmount($since, $request, $context);
        static::assertSame('{"statistic":[{"date":"2021-01-01","count":100,"amount":1300.3},{"date":"2021-01-02","count":200,"amount":2600.1}]}', $response->getContent());
    }

    public static function requestProvider(): \Generator
    {
        yield 'Since today, paid fallback, timezone fallback' => [
            'request' => new Request(),
            'since' => 'today',
            'paid' => true,
            'timezone' => 'UTC',
        ];

        yield 'Since today, paid true, timezone fallback' => [
            'request' => new Request(['paid' => 'true']),
            'since' => 'today',
            'paid' => true,
            'timezone' => 'UTC',
        ];

        yield 'Since today, paid false, timezone fallback' => [
            'request' => new Request(['paid' => 'false']),
            'since' => 'today',
            'paid' => false,
            'timezone' => 'UTC',
        ];

        yield 'Since today, paid fallback, timezone Europe/Berlin' => [
            'request' => new Request(['timezone' => 'Europe/Berlin']),
            'since' => 'today',
            'paid' => true,
            'timezone' => 'Europe/Berlin',
        ];

        yield 'Since today, paid true, timezone Europe/Berlin' => [
            'request' => new Request(['paid' => 'true', 'timezone' => 'Europe/Berlin']),
            'since' => 'today',
            'paid' => true,
            'timezone' => 'Europe/Berlin',
        ];

        yield 'Since today, paid false, timezone Europe/Berlin' => [
            'request' => new Request(['paid' => 'false', 'timezone' => 'Europe/Berlin']),
            'since' => 'today',
            'paid' => false,
            'timezone' => 'Europe/Berlin',
        ];

        yield 'Only query parameter considered' => [
            'request' => new Request([], ['paid' => 'false', 'timezone' => 'Europe/Berlin']),
            'since' => 'today',
            'paid' => true,
            'timezone' => 'UTC',
        ];
    }
}
