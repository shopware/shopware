<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Theme\SeedingThemePathBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\SeedingThemePathBuilder
 */
class SeedingThemePathBuilderTest extends TestCase
{
    /**
     * @dataProvider assemblePathProvider
     */
    public function testAssemblePath(Request $request, Connection&MockObject $connection): void
    {
        $stack = new RequestStack();
        $stack->push($request);

        $builder = new SeedingThemePathBuilder($stack, $connection);
        $path = $builder->assemblePath('2d8c52ed82da148cd9d4668f971924bf', '1922c90708f8d1f2bf23bc0fa28f6be4');

        static::assertEquals('258d9243af292171dd32d83890fd4059', $path);
    }

    public function testGenerateNewPathEqualsAssemblePath(): void
    {
        $builder = new SeedingThemePathBuilder(new RequestStack(), $this->createMock(Connection::class));
        $path = $builder->generateNewPath('salesChannelId', 'themeId', 'foo');

        static::assertEquals('2d8c52ed82da148cd9d4668f971924bf', $path);

        $path = $builder->generateNewPath('salesChannelId', 'themeId', 'bar');

        static::assertEquals('1922c90708f8d1f2bf23bc0fa28f6be4', $path);
    }

    public function testGenerateNewPathEqualsIgnoresSeed(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('executeStatement');

        $builder = new SeedingThemePathBuilder(new RequestStack(), $connection);

        $builder->saveSeed(Uuid::randomHex(), Uuid::randomHex(), 'foo');
    }

    public function assemblePathProvider(): \Generator
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::never())->method('fetchOne');

        yield 'seed present in request' => [
            new Request([], [], [
                SalesChannelRequest::ATTRIBUTE_THEME_HASH => 'foo',
            ]),
            $connection,
        ];

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn('foo');

        yield 'seed not present in request' => [
            new Request(),
            $connection,
        ];
    }
}
