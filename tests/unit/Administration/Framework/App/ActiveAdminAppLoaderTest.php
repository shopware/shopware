<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\App;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\App\ActiveAdminAppLoader;

/**
 * @internal
 */
#[CoversClass(ActiveAdminAppLoader::class)]
class ActiveAdminAppLoaderTest extends TestCase
{
    #[TestDox('Returns an empty list when no active admin apps are present')]
    public function testReturnsEmptyListWhenNoRows(): void
    {
        $loader = new ActiveAdminAppLoader($this->stubConnection([]));

        static::assertSame([], $loader->getActiveAdminApps());
    }

    /**
     * @param ?string $rawPrivileges value of `acl_role.privileges` as stored in the database
     * @param array<string, list<string>> $expected normalised privileges keyed by ACL action (or "additional" for malformed entries)
     */
    #[TestDox('Normalises the JSON privileges column: $_dataName')]
    #[DataProvider('privilegesProvider')]
    public function testPrivilegesNormalization(?string $rawPrivileges, array $expected): void
    {
        $loader = new ActiveAdminAppLoader($this->stubConnection([
            [
                'name' => 'SomeApp',
                'active' => 1,
                'integrationId' => 'abc',
                'baseUrl' => 'https://app.test',
                'version' => '1.0.0',
                'privileges' => $rawPrivileges,
            ],
        ]));

        $apps = $loader->getActiveAdminApps();

        static::assertCount(1, $apps);
        static::assertSame($expected, $apps[0]['privileges']);
    }

    /**
     * @return \Generator<string, array{0: ?string, 1: array<string, list<string>>}>
     */
    public static function privilegesProvider(): \Generator
    {
        yield 'null privileges => empty array' => [
            null,
            [],
        ];

        yield 'empty JSON array => empty array' => [
            '[]',
            [],
        ];

        yield 'entity:action privileges grouped by action key' => [
            '["product:read","product:write","order:read"]',
            [
                'read' => ['product', 'order'],
                'write' => ['product'],
            ],
        ];

        yield 'privileges without a colon land in additional' => [
            '["app.config"]',
            ['additional' => ['app.config']],
        ];

        yield 'privileges with more than one colon also land in additional' => [
            '["entity:action:scope"]',
            ['additional' => ['entity:action:scope']],
        ];
    }

    /**
     * @param list<array{name: string, active: int, integrationId: string, baseUrl: string, version: string, privileges: ?string}> $rows
     */
    private function stubConnection(array $rows): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        return $connection;
    }
}
