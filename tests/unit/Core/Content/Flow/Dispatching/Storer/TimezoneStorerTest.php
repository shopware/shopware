<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\TimezoneStorer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub\MailAwareEvent;
use Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub\NonMailAwareEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(TimezoneStorer::class)]
class TimezoneStorerTest extends TestCase
{
    /**
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $expected
     */
    #[DataProvider('storeDataProvider')]
    public function testStore(FlowEventAware $event, Request $request, array $stored, array $expected): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack
            ->method('getCurrentRequest')
            ->willReturn($request);

        $storer = new TimezoneStorer($requestStack);

        $stored = $storer->store($event, $stored);

        static::assertSame($expected, $stored);
    }

    public static function storeDataProvider(): \Generator
    {
        $request = new Request();
        $request->cookies->set(TimezoneStorer::TIMEZONE_COOKIE, 'Europe/Berlin');

        yield 'store timezone for mail aware event' => [
            'event' => new MailAwareEvent(),
            'request' => $request,
            'stored' => [],
            'expected' => [MailAware::TIMEZONE => 'Europe/Berlin'],
        ];

        yield 'overwrite stored timezone for mail aware event' => [
            'event' => new MailAwareEvent(),
            'request' => $request,
            'stored' => [MailAware::TIMEZONE => 'UTC'],
            'expected' => [MailAware::TIMEZONE => 'Europe/Berlin'],
        ];

        yield 'ignore non mail aware event' => [
            'event' => new NonMailAwareEvent(),
            'request' => $request,
            'stored' => [],
            'expected' => [],
        ];

        yield 'null request should use default timezone' => [
            'event' => new MailAwareEvent(),
            'request' => new Request(),
            'stored' => [],
            'expected' => [MailAware::TIMEZONE => 'UTC'],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('restoreDataProvider')]
    public function testRestore(StorableFlow $flow, array $expected): void
    {
        $storer = new TimezoneStorer(new RequestStack());
        $storer->restore($flow);

        static::assertSame($expected, $flow->data());
    }

    public static function restoreDataProvider(): \Generator
    {
        yield 'restore empty' => [
            'flow' => new StorableFlow('foo', Context::createDefaultContext(), []),
            'expected' => [],
        ];

        yield 'restore id' => [
            'flow' => new StorableFlow('foo', Context::createDefaultContext(), [
                MailAware::TIMEZONE => 'Europe/Berlin',
            ]),
            'expected' => [MailAware::TIMEZONE => 'Europe/Berlin'],
        ];

        yield 'restore null' => [
            'flow' => new StorableFlow('foo', Context::createDefaultContext(), [
                MailAware::TIMEZONE => 'Europe/Berlin',
            ]),
            'expected' => [MailAware::TIMEZONE => 'Europe/Berlin'],
        ];
    }
}
