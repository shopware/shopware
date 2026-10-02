<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin;

use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminElasticsearchOutdatedIndexDetector;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AdminElasticsearchOutdatedIndexDetector::class)]
class AdminElasticsearchOutdatedIndexDetectorTest extends TestCase
{
    private const THRESHOLD = '2026-08-11 12:00:00';

    public function testGetReturnsOnlyIndicesWithoutAlias(): void
    {
        $detector = $this->createDetector([
            'sw-admin-product_1000' => $this->index('sw-admin-product_1000', ['sw-admin-product'], '2020-01-01 00:00:00'),
            'sw-admin-product_2000' => $this->index('sw-admin-product_2000', [], '2020-01-01 00:00:00'),
        ]);

        static::assertSame(['sw-admin-product_2000'], $detector->get());
    }

    public function testGetIgnoresTheAgeOfAnIndex(): void
    {
        $detector = $this->createDetector([
            'sw-admin-product_2000' => $this->index('sw-admin-product_2000', [], '2026-08-12 11:59:00'),
        ]);

        static::assertSame(['sw-admin-product_2000'], $detector->get());
    }

    public function testGetOutdatedKeepsIndicesCreatedAfterTheThreshold(): void
    {
        $detector = $this->createDetector([
            'sw-admin-product_1000' => $this->index('sw-admin-product_1000', [], '2026-08-10 12:00:00'),
            'sw-admin-product_2000' => $this->index('sw-admin-product_2000', [], '2026-08-12 11:59:00'),
        ]);

        static::assertSame(['sw-admin-product_1000'], $detector->getOutdated(new \DateTimeImmutable(self::THRESHOLD)));
    }

    public function testGetOutdatedKeepsIndicesWithoutCreationDate(): void
    {
        $index = $this->index('sw-admin-product_1000', [], '2020-01-01 00:00:00');
        unset($index['settings']['index']['creation_date']);

        $detector = $this->createDetector(['sw-admin-product_1000' => $index]);

        static::assertSame([], $detector->getOutdated(new \DateTimeImmutable(self::THRESHOLD)));
    }

    public function testGetOutdatedKeepsAliasedIndicesRegardlessOfAge(): void
    {
        $detector = $this->createDetector([
            'sw-admin-product_1000' => $this->index('sw-admin-product_1000', ['sw-admin-product'], '2020-01-01 00:00:00'),
        ]);

        static::assertSame([], $detector->getOutdated(new \DateTimeImmutable(self::THRESHOLD)));
    }

    /**
     * @param array<string, array{aliases: array<string>, settings: array<mixed>}> $indices
     */
    private function createDetector(array $indices): AdminElasticsearchOutdatedIndexDetector
    {
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('get')
            ->with(['index' => 'sw-admin*'])
            ->willReturn($indices);

        $client = static::createStub(Client::class);
        $client->method('indices')->willReturn($indicesNamespace);

        $helper = static::createStub(AdminElasticsearchHelper::class);
        $helper->method('getPrefix')->willReturn('sw-admin');

        return new AdminElasticsearchOutdatedIndexDetector($client, $helper);
    }

    /**
     * @param array<string> $aliases
     *
     * @return array{aliases: array<string>, settings: array<mixed>}
     */
    private function index(string $name, array $aliases, string $createdAt): array
    {
        return [
            'aliases' => $aliases,
            'settings' => [
                'index' => [
                    'provided_name' => $name,
                    'creation_date' => (string) ((new \DateTimeImmutable($createdAt))->getTimestamp() * 1000),
                ],
            ],
        ];
    }
}
