<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\DashboardController;
use Shopware\Administration\Dashboard\OrderAmountService;
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

        $service->expects(static::once())
            ->method('load')
            ->with($since, $paid, $timezone);

        $controller = new DashboardController($service);

        $controller->orderAmount($since, $request);
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
