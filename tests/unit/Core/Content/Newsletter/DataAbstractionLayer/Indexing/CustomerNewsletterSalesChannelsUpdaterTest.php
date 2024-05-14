<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\Indexing\CustomerNewsletterSalesChannelsUpdater;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CustomerNewsletterSalesChannelsUpdater::class)]
class CustomerNewsletterSalesChannelsUpdaterTest extends TestCase
{
    #[DataProvider('dataProvider')]
    public function testUpdateCustomersRecipient(?string $newsletterIds, \Closure $expectsClosure): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            [
                'email' => 'y.tran@shopware.com',
                'last_name' => 'Tran',
                'first_name' => 'Y',
                'newsletter_sales_channel_ids' => $newsletterIds,
            ],
        ]);

        $expectsClosure(
            $connection,
            $newsletterIds ? array_keys(json_decode($newsletterIds, true, 512, \JSON_THROW_ON_ERROR)) : null
        );

        $indexing = new CustomerNewsletterSalesChannelsUpdater($connection);
        $indexing->updateCustomersRecipient([Uuid::randomHex()]);
    }

    public static function dataProvider(): \Generator
    {
        yield 'Email Newsletter Recipient Registered' => [
            'newsletterIds' => json_encode([Uuid::randomHex() => Uuid::randomHex()], \JSON_THROW_ON_ERROR),
            function (MockObject $connection, ?array $ids): void {
                $connection->expects(static::once())->method('executeStatement')->willReturnCallback(function ($sql, $params) use ($ids): void {
                    static::assertSame('UPDATE newsletter_recipient SET email = (:email), first_name = (:firstName), last_name = (:lastName) WHERE id IN (:ids)', $sql);

                    static::assertNotNull($ids);
                    static::assertSame([
                        'ids' => Uuid::fromHexToBytesList($ids),
                        'email' => 'y.tran@shopware.com',
                        'firstName' => 'Y',
                        'lastName' => 'Tran',
                    ], $params);
                });
            },
        ];

        yield 'Email Newsletter Recipient Registered Multiple' => [
            'newsletterIds' => json_encode([Uuid::randomHex() => Uuid::randomHex(), Uuid::randomHex() => Uuid::randomHex()], \JSON_THROW_ON_ERROR),
            function (MockObject $connection, ?array $ids): void {
                $connection->expects(static::once())->method('executeStatement')->willReturnCallback(function ($sql, $params) use ($ids): void {
                    static::assertSame('UPDATE newsletter_recipient SET email = (:email), first_name = (:firstName), last_name = (:lastName) WHERE id IN (:ids)', $sql);

                    static::assertNotNull($ids);
                    static::assertSame([
                        'ids' => Uuid::fromHexToBytesList($ids),
                        'email' => 'y.tran@shopware.com',
                        'firstName' => 'Y',
                        'lastName' => 'Tran',
                    ], $params);
                });
            },
        ];

        yield 'Email Newsletter Recipient Not Registered' => [
            'newsletterIds' => null,
            function (MockObject $connection): void {
                $connection->expects(static::never())->method('executeUpdate');
            },
        ];
    }
}
