<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Log\Monolog;

use Monolog\Handler\FingersCrossedHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Framework\Log\Monolog\ExcludeFlowEventHandler;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;

/**
 * @internal
 */
#[CoversClass(ExcludeFlowEventHandler::class)]
class ExcludeFlowEventHandlerTest extends TestCase
{
    /**
     * @param list<string> $excludeList
     */
    #[DataProvider('cases')]
    public function testHandler(LogRecord $record, array $excludeList, bool $shouldBePassed): void
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
     * @return iterable<string, array{0: LogRecord, 1: list<string>, 2: bool}>
     */
    public static function cases(): iterable
    {
        // record, exclude list, should be passed
        yield 'event without exclude list' => [
            new LogRecord(new \DateTimeImmutable(), 'foo', Level::Alert, 'some message'),
            [],
            true,
        ];

        yield 'event with exclude list that matches but different channel' => [
            new LogRecord(new \DateTimeImmutable(), 'app', Level::Alert, UserRecoveryRequestEvent::EVENT_NAME),
            [
                UserRecoveryRequestEvent::EVENT_NAME,
            ],
            true,
        ];

        yield 'event with exclude list that matches' => [
            new LogRecord(new \DateTimeImmutable(), 'business_events', Level::Alert, UserRecoveryRequestEvent::EVENT_NAME),
            [
                UserRecoveryRequestEvent::EVENT_NAME,
            ],
            false,
        ];

        yield 'event with exclude list that not matches' => [
            new LogRecord(new \DateTimeImmutable(), 'business_events', Level::Alert, UserRecoveryRequestEvent::EVENT_NAME),
            [
                CustomerAccountRecoverRequestEvent::EVENT_NAME,
            ],
            true,
        ];

        yield 'mail event with exclude list that matches' => [
            new LogRecord(
                new \DateTimeImmutable(),
                'business_events',
                Level::Alert,
                MailSentEvent::EVENT_NAME,
                [
                    'additionalData' => [
                        'eventName' => CustomerAccountRecoverRequestEvent::EVENT_NAME,
                    ],
                ]
            ),
            [
                CustomerAccountRecoverRequestEvent::EVENT_NAME,
            ],
            false,
        ];

        yield 'mail event with exclude list that not matches' => [
            new LogRecord(
                new \DateTimeImmutable(),
                'business_events',
                Level::Alert,
                MailSentEvent::EVENT_NAME,
                [
                    'additionalData' => [
                        'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
                    ],
                ]
            ),
            [
                CustomerAccountRecoverRequestEvent::EVENT_NAME,
            ],
            true,
        ];

        yield 'mail event with exclude list without additionalData' => [
            new LogRecord(
                new \DateTimeImmutable(),
                'business_events',
                Level::Alert,
                MailSentEvent::EVENT_NAME,
            ),
            [
                CustomerAccountRecoverRequestEvent::EVENT_NAME,
            ],
            true,
        ];
    }
}
