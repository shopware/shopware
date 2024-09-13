<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;

/**
 * @internal
 */
#[CoversClass(AdminElasticsearchHelper::class)]
class AdminElasticsearchHelperTest extends TestCase
{
    #[DataProvider('searchHelperProvider')]
    public function testSearchHelper(bool $adminEsEnabled, bool $refreshIndices, string $adminIndexPrefix): void
    {
        $searchHelper = new AdminElasticsearchHelper($adminEsEnabled, $refreshIndices, $adminIndexPrefix);

        static::assertEquals($adminEsEnabled, $searchHelper->getEnabled());
        static::assertEquals($refreshIndices, $searchHelper->getRefreshIndices());
        static::assertEquals($adminIndexPrefix, $searchHelper->getPrefix());
        static::assertEquals($adminIndexPrefix . '-promotion-listing', $searchHelper->getIndex('promotion-listing'));
    }

    public function testSetEnable(): void
    {
        $searchHelper = new AdminElasticsearchHelper(false, false, 'sw-admin');

        static::assertFalse($searchHelper->getEnabled());
        static::assertFalse($searchHelper->getRefreshIndices());
        static::assertEquals('sw-admin', $searchHelper->getPrefix());
        static::assertEquals('sw-admin-promotion-listing', $searchHelper->getIndex('promotion-listing'));

        $searchHelper->setEnabled(true);

        static::assertTrue($searchHelper->getEnabled());
    }

    public static function searchHelperProvider(): \Generator
    {
        yield 'Not enable ES and not refresh indices' => [
            false,
            false,
            'sw-admin',
        ];

        yield 'Enable ES and not refresh indices' => [
            true,
            false,
            'sw-admin',
        ];

        yield 'Enable ES and refresh indices' => [
            true,
            true,
            'sw-admin',
        ];
    }
}
