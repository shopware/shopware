<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiException;
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
            'operations' => [
                [
                    'key' => 'invalid-entity',
                    'entity' => '',
                    'action' => 'upsert',
                    'payload' => [['id' => 'id1', 'name' => 'first manufacturer']],
                ],
            ],
            'errors' => [
                'Missing "entity" argument for operation with key "invalid-entity". It needs to be a non-empty string.',
            ],
        ];

        yield 'Missing action argument' => [
            'operations' => [
                [
                    'key' => 'missing-action',
                    'entity' => 'entity-name',
                    'action' => '',
                    'payload' => [
                        ['id' => 'id1', 'name' => 'first manufacturer'],
                        ['id' => 'id2', 'name' => 'second manufacturer'],
                    ],
                ],
            ],
            'errors' => [
                'Missing or invalid "action" argument for operation with key "missing-action". Supported actions are [upsert, delete]',
            ],
        ];

        yield 'Invalid action argument' => [
            'operations' => [
                [
                    'key' => 'invalid-action',
                    'entity' => 'entity-name',
                    'action' => 'invalid-action',
                    'payload' => [
                        ['id' => 'id1', 'name' => 'first manufacturer'],
                        ['id' => 'id2', 'name' => 'second manufacturer'],
                    ],
                ],
            ],
            'errors' => [
                'Missing or invalid "action" argument for operation with key "invalid-action". Supported actions are [upsert, delete]',
            ],
        ];

        yield 'Missing payload argument' => [
            'operations' => [
                [
                    'key' => 'missing-payload',
                    'entity' => 'entity-name',
                    'action' => 'upsert',
                    'payload' => [],
                ],
            ],
            'errors' => [
                'Missing "payload"|"criteria" argument for operation with key "missing-payload". It needs to be a non-empty array.',
            ],
        ];

        yield 'Missing entity and action arguments' => [
            'operations' => [
                [
                    'key' => 'missing-both',
                    'entity' => '',
                    'action' => '',
                    'payload' => [['id' => 'id1', 'name' => 'first manufacturer']],
                ],
            ],
            'errors' => [
                'Missing "entity" argument for operation with key "missing-both". It needs to be a non-empty string.; Missing or invalid "action" argument for operation with key "missing-both". Supported actions are [upsert, delete]',
            ],
        ];
    }

    public static function validOperationProvider(): \Generator
    {
        yield 'Valid Operation with Key' => [
            [
                'key' => 'test-key',
                'entity' => 'product',
                'action' => 'upsert',
                'payload' => [['id' => '123', 'name' => 'Test Product']],
                'criteria' => [],
            ],
            'test-key',
        ];

        yield 'Valid Operation with Fallback Key' => [
            [
                'entity' => 'product',
                'action' => 'upsert',
                'payload' => [['id' => '123', 'name' => 'Test Product']],
                'criteria' => [],
            ],
            'fallback-key',
        ];
    }

    /**
     * @param array<string, mixed> $operation
     */
    #[DataProvider('validOperationProvider')]
    public function testCreateFromArrayWithValidInput(array $operation, string $expectedKey): void
    {
        $syncOperation = SyncOperation::createFromArray($operation, 'fallback-key');

        static::assertSame($expectedKey, $syncOperation->getKey());
    }

    /**
     * @param array<mixed> $operations
     * @param array<string> $expectedErrors
     */
    #[DataProvider('invalidOperationProvider')]
    public function testCreateFromArrayThrowsExceptionForInvalidInput(array $operations, array $expectedErrors): void
    {
        $caughtExceptions = 0;

        foreach ($operations as $index => $operation) {
            try {
                SyncOperation::createFromArray($operation, $operation['key'] ?? 'fallback-key');
            } catch (ApiException $exception) {
                static::assertContains($exception->getMessage(), $expectedErrors, "Error mismatch for operation index {$index}");
                ++$caughtExceptions;

                continue;
            }
            static::fail("Expected an exception for operation index {$index} but none was thrown.");
        }

        static::assertSame(\count($expectedErrors), $caughtExceptions, 'The number of caught exceptions does not match the expected number of errors.');
    }
}
