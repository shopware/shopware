<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\Staging\Handler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\Maintenance\Staging\Handler\StagingSalesChannelHandler;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[CoversClass(StagingSalesChannelHandler::class)]
class StagingSalesChannelHandlerTest extends TestCase
{
    public function testReplaceByEqual(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::once())
            ->method('fetchAllAssociative')->willReturn([
                ['id' => 'id1', 'url' => 'http://localhost'],
            ]);

        $connection
            ->expects(static::once())
            ->method('update')->with(
                'sales_channel_domain',
                ['url' => 'http://staging.local'],
                ['id' => 'id1']
            );

        $handler = new StagingSalesChannelHandler(
            [
                ['match' => 'http://localhost', 'type' => 'equal', 'replace' => 'http://staging.local'],
            ],
            $connection
        );

        $event = new SetupStagingEvent(Context::createDefaultContext(), $this->createMock(SymfonyStyle::class));

        $handler($event);
    }

    public function testReplaceEqualNoMatch(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::once())
            ->method('fetchAllAssociative')->willReturn([
                ['id' => 'id1', 'url' => 'http://localhost'],
            ]);

        $connection
            ->expects(static::never())
            ->method('update');

        $handler = new StagingSalesChannelHandler(
            [
                ['match' => 'http://fooo', 'type' => 'equal', 'replace' => 'http://staging.local'],
            ],
            $connection
        );

        $event = new SetupStagingEvent(Context::createDefaultContext(), $this->createMock(SymfonyStyle::class));

        $handler($event);
    }

    public function testReplaceByRegexp(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::once())
            ->method('fetchAllAssociative')->willReturn([
                ['id' => 'id1', 'url' => 'https://pikachu.com'],
            ]);

        $connection
            ->expects(static::once())
            ->method('update')->with(
                'sales_channel_domain',
                ['url' => 'http://pikachu-com.local'],
                ['id' => 'id1']
            );

        $handler = new StagingSalesChannelHandler(
            [
                ['match' => '/https?:\/\/(\w+)\.(\w+)$/m', 'type' => 'regex', 'replace' => 'http://$1-$2.local'],
            ],
            $connection
        );

        $event = new SetupStagingEvent(Context::createDefaultContext(), $this->createMock(SymfonyStyle::class));

        $handler($event);
    }

    public function testReplaceByPrefix(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::once())
            ->method('fetchAllAssociative')->willReturn([
                ['id' => 'id1', 'url' => 'https://pikachu.com/en'],
            ]);

        $connection
            ->expects(static::once())
            ->method('update')->with(
                'sales_channel_domain',
                ['url' => 'http://localhost/en'],
                ['id' => 'id1']
            );

        $handler = new StagingSalesChannelHandler(
            [
                ['match' => 'https://pikachu.com', 'type' => 'prefix', 'replace' => 'http://localhost'],
            ],
            $connection
        );

        $event = new SetupStagingEvent(Context::createDefaultContext(), $this->createMock(SymfonyStyle::class));

        $handler($event);
    }
}
