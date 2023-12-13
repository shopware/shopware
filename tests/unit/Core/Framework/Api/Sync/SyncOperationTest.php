<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Sync\SyncOperation;

/**
 * @internal
 */
#[CoversClass(SyncOperation::class)]
class SyncOperationTest extends TestCase
{
    public function testWithValidOperation(): void
    {
        $operation = new SyncOperation(
            'valid-operation',
            'entity-name',
            'upsert',
            [
                ['id' => 'id1', 'name' => 'first manufacturer'],
                ['id' => 'id2', 'name' => 'second manufacturer'],
            ]
        );

        static::assertEmpty($operation->validate());
    }

    public static function invalidOperationProvider(): \Generator
    {
        yield 'Invalid entity argument' => [
            'invalid-entity',
            '',
            'upsert',
            [
                ['id' => 'id1', 'name' => 'first manufacturer'],
                ['id' => 'id2', 'name' => 'second manufacturer'],
            ],
            'entity',
        ];

        yield 'Missing action argument' => [
            'missing-action',
            'entity-name',
            '',
            [
                ['id' => 'id1', 'name' => 'first manufacturer'],
                ['id' => 'id2', 'name' => 'second manufacturer'],
            ],
            'action',
        ];

        yield 'Invalid action argument' => [
            'missing-action',
            'entity-name',
            'invalid-action',
            [
                ['id' => 'id1', 'name' => 'first manufacturer'],
                ['id' => 'id2', 'name' => 'second manufacturer'],
            ],
            'action',
        ];

        yield 'Missing payload argument' => [
            'missing-action',
            'entity-name',
            'upsert',
            [],
            'payload',
        ];
    }

    /**
     * @param array<mixed> $payload
     */
    #[DataProvider('invalidOperationProvider')]
    public function testWithInvalidOperation(string $key, string $entity, string $action, array $payload, string $actor): void
    {
        $operation = new SyncOperation($key, $entity, $action, $payload);

        $errors = $operation->validate();
        static::assertNotEmpty($errors);
        static::assertCount(1, $errors);
        static::assertStringContainsString($actor, $errors[0]);
    }
}
