<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class Migration1603970276RemoveCustomerEmailUniqueConstraintTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCustomerEmailUniqueConstraintIsRemoved(): void
    {
        /** @var Connection $conn */
        $conn = $this->getContainer()->get(Connection::class);
        $indexes = $conn->fetchAll('SHOW INDEX FROM `customer`;') ?? [];

        $emailUniqueConstraint = array_filter($indexes, function (array $index) {
            return !empty($index['Key_name']) && $index['Key_name'] === 'uniq.customer.email_bound_sales_channel_id';
        });

        static::assertEmpty($emailUniqueConstraint);
    }
}
