<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Log\Monolog;

use Monolog\Handler\FingersCrossedHandler;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Framework\Log\Monolog\ExcludeFlowEventHandler;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Log\Monolog\ExcludeFlowEventHandler
 */
class ExcludeFlowEventHandlerTest extends TestCase
{
    /**
     * @param array{message: string, context: array<mixed>, level: 100|200|250|300|400|500|550|600, level_name: 'ALERT'|'CRITICAL'|'DEBUG'|'EMERGENCY'|'ERROR'|'INFO'|'NOTICE'|'WARNING', channel: string, datetime: \DateTimeImmutable, extra: array<mixed>} $record
     * @param array<int, string> $excludeList
     *
     * @dataProvider cases
     */
    public function testHandler(array $record, array $excludeList, bool $shouldBePassed): void
    {
        $innerHandler = $this->createMock(FingersCrossedHandler::class);
        $innerHandler->expects($shouldBePassed ? static::once() : static::never())->method('handle')->willReturn(true);

        $handler = new ExcludeFlowEventHandler(
            $innerHandler,
            $excludeList
        );

        $handler->handle($record);
    }

    /**
     * @return iterable<string, array<int, bool|array<mixed>>>
     */
    public function cases(): iterable
    {
        // record, exclude list, should be passed
        yield 'empty record' => [
            [],
            [],
            true,
        ];

        yield 'event without exclude list' => [
            [
                'message' => 'some message',
            ],
            [],
            true,
        ];

        yield 'event with exclude list that matches but different channel' => [
            [
                'channel' => 'app',
                'message' => 'user.recovery.request',
            ],
            [
                UserRecoveryRequestEvent::EVENT_NAME,
            ],
            true,
        ];

        yield 'event with exclude list that matches' => [
            [
                'channel' => 'business_events',
                'message' => 'user.recovery.request',
            ],
            [
                UserRecoveryRequestEvent::EVENT_NAME,
            ],
            false,
        ];

        yield 'event with exclude list that not matches' => [
            [
                'channel' => 'business_events',
                'message' => 'user.recovery.request',
            ],
            [
                CustomerAccountRecoverRequestEvent::EVENT_NAME,
            ],
            true,
        ];

        yield 'mail event with exclude list that matches' => [
            [
                'channel' => 'business_events',
                'message' => 'mail.sent',
                'context' => [
                    'additionalData' => [
                        'eventName' => CustomerAccountRecoverRequestEvent::EVENT_NAME,
                    ],
                ],
            ],
            [
                CustomerAccountRecoverRequestEvent::EVENT_NAME,
            ],
            false,
        ];

        yield 'mail event with exclude list that not matches' => [
            [
                'channel' => 'business_events',
                'message' => 'mail.sent',
                'context' => [
                    'additionalData' => [
                        'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
                    ],
                ],
            ],
            [
                CustomerAccountRecoverRequestEvent::EVENT_NAME,
            ],
            true,
        ];

        yield 'mail event with exclude list without additionalData' => [
            [
                'channel' => 'business_events',
                'message' => 'mail.sent',
                'context' => [],
            ],
            [
                CustomerAccountRecoverRequestEvent::EVENT_NAME,
            ],
            true,
        ];
    }
}
