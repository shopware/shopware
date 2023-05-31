<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Api\Controller\SyncController
 */
class SyncControllerTest extends TestCase
{
    public function testRequestGetsConverted(): void
    {
        $criteria = [
            [
                'type' => 'or',
                'queries' => [
                    ['type' => 'equals', 'field' => 'categoryId', 'value' => 'foo'],
                    ['type' => 'equalsAny', 'field' => 'productId', 'value' => ['bar']],
                ],
            ],
        ];

        $operations = [
            'delete-mapping' => [
                'action' => 'delete',
                'entity' => 'product',
                'criteria' => $criteria,
            ],
        ];

        $request = new Request([], [], [], [], [], [], (string) \json_encode($operations));

        $service = $this->createMock(SyncService::class);
        $service->expects(static::once())
            ->method('sync')
            ->willReturnCallback(function ($operations) use ($criteria) {
                static::assertCount(1, $operations);
                static::assertInstanceOf(SyncOperation::class, $operations[0]);

                $operation = $operations[0];
                static::assertSame('delete-mapping', $operation->getKey());
                static::assertSame('product', $operation->getEntity());
                static::assertSame('delete', $operation->getAction());
                static::assertEquals($criteria, $operation->getCriteria());

                return new SyncResult([]);
            });

        $controller = new SyncController($service, new Serializer([], [new JsonEncoder()]));

        $controller->sync($request, Context::createDefaultContext());
    }
}
