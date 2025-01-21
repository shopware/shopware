<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\Indexing\CustomerNewsletterSalesChannelsUpdater;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(CustomerNewsletterSalesChannelsUpdater::class)]
class CustomerNewsletterSalesChannelsUpdaterTest extends TestCase
{
    private MockObject&Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->createMock(Connection::class);
    }

    public function testUpdateCustomersRecipientWithoutNewsletterSalesChannelIds(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'email' => 'y.tran@shopware.com',
                'last_name' => 'Tran',
                'first_name' => 'Y',
                'newsletter_sales_channel_ids' => null,
            ],
        ]);

        $this->connection->expects(static::never())->method('executeUpdate');

        $indexing = new CustomerNewsletterSalesChannelsUpdater($this->connection);
        $indexing->updateCustomersRecipient([Uuid::randomHex()]);
    }

    public function testUpdateCustomersRecipientWithRegisteredEmailNewsletterRecipient(): void
    {
        $newsletterIds = json_encode([Uuid::randomHex() => Uuid::randomHex()], \JSON_THROW_ON_ERROR);
        static::assertIsString($newsletterIds);
        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'email' => 'y.tran@shopware.com',
                'last_name' => 'Tran',
                'first_name' => 'Y',
                'newsletter_sales_channel_ids' => $newsletterIds,
            ],
        ]);

        $ids = $this->getNewsLetterIds($newsletterIds);

        $this->connection->expects(static::once())->method('executeStatement')->willReturnCallback(function ($sql, $params) use ($ids): void {
            static::assertSame('UPDATE newsletter_recipient SET email = (:email), first_name = (:firstName), last_name = (:lastName) WHERE id IN (:ids)', $sql);

            static::assertSame([
                'ids' => Uuid::fromHexToBytesList($ids),
                'email' => 'y.tran@shopware.com',
                'firstName' => 'Y',
                'lastName' => 'Tran',
            ], $params);
        });

        $indexing = new CustomerNewsletterSalesChannelsUpdater($this->connection);
        $indexing->updateCustomersRecipient([Uuid::randomHex()]);
    }

    public function testUpdateCustomersRecipientWithMultipleRegisteredEmailNewsletterRecipient(): void
    {
        $newsletterIds = json_encode([Uuid::randomHex() => Uuid::randomHex(), Uuid::randomHex() => Uuid::randomHex()], \JSON_THROW_ON_ERROR);
        static::assertIsString($newsletterIds);
        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'email' => 'y.tran@shopware.com',
                'last_name' => 'Tran',
                'first_name' => 'Y',
                'newsletter_sales_channel_ids' => $newsletterIds,
            ],
        ]);

        $ids = $this->getNewsLetterIds($newsletterIds);
        $this->connection->expects(static::once())->method('executeStatement')->willReturnCallback(function ($sql, $params) use ($ids): void {
            static::assertSame('UPDATE newsletter_recipient SET email = (:email), first_name = (:firstName), last_name = (:lastName) WHERE id IN (:ids)', $sql);

            static::assertSame([
                'ids' => Uuid::fromHexToBytesList($ids),
                'email' => 'y.tran@shopware.com',
                'firstName' => 'Y',
                'lastName' => 'Tran',
            ], $params);
        });

        $indexing = new CustomerNewsletterSalesChannelsUpdater($this->connection);
        $indexing->updateCustomersRecipient([Uuid::randomHex()]);
    }

    /**
     * @throws \JsonException
     *
     * @return array<int, string>
     */
    private function getNewsLetterIds(string $newsletterIds): array
    {
        $result = [];
        $ids = array_keys(json_decode($newsletterIds, true, 512, \JSON_THROW_ON_ERROR));

        foreach ($ids as $key => $value) {
            $result[$key] = (string) $value;
        }

        return $result;
    }
}
