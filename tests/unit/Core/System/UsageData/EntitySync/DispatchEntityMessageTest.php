<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\EntitySync;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\Operation;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\EntitySync\DispatchEntityMessage
 */
#[Package('data-services')]
class DispatchEntityMessageTest extends TestCase
{
    public function testGetters(): void
    {
        $message = new DispatchEntityMessage(
            $entityName = 'product_category',
            $operation = Operation::CREATE,
            $runDate = new \DateTimeImmutable('2023-08-11'),
            $ids = [
                ['product_id' => 'product-id-1', 'category_id' => 'category_id_1'],
                ['product_id' => 'product-id-2', 'category_id' => 'category_id_2'],
            ]
        );

        static::assertSame($entityName, $message->getEntityName());
        static::assertSame($operation, $message->getOperation());
        static::assertSame($runDate, $message->getRunDate());
        static::assertSame($ids, $message->getPrimaryKeys());
    }
}
