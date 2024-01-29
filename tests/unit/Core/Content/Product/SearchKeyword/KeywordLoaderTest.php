<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SearchKeyword;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SearchKeyword\KeywordLoader;
use Shopware\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(KeywordLoader::class)]
class KeywordLoaderTest extends TestCase
{
    public function testFetch(): void
    {
        $slops = ['foo', 'bar'];

        $tokenSlops = [[
            'normal' => [$slops[0]],
            'reversed' => [$slops[1]],
        ]];

        $connection = static::createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQL80Platform());
        $connection->expects(static::once())
            ->method('executeQuery')
            ->with(static::anything(), static::callback(function (array $params) use ($slops) {
                foreach ($slops as $slop) {
                    static::assertContains($slop, $params);
                }

                return true;
            }));

        (new KeywordLoader($connection))->fetch($tokenSlops, Context::createDefaultContext());
    }
}
