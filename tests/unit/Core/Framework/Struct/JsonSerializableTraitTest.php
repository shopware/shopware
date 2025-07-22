<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('framework')]
#[CoversTrait(JsonSerializableTrait::class)]
class JsonSerializableTraitTest extends TestCase
{
    public function test(): void
    {
        static::assertEquals(
            [
                'value' => 1,
                'testEntity' => [
                    'name' => 'example-name',
                    'nonStruct' => ['non-struct-key' => 'non-struct-value'],
                    'createdAt' => '2025-01-01T00:00:00.000+00:00',
                    'extensions' => [],
                ],
            ],
            (new SerializableClass())->jsonSerialize(),
        );
    }
}

/**
 * @internal
 */
class NonStruct implements \JsonSerializable
{
    public function jsonSerialize(): array
    {
        return ['non-struct-key' => 'non-struct-value'];
    }
}

/**
 * @internal
 */
class TestEntity extends Struct
{
    public function __construct(
        private string $state = 'unprocessed',
        protected string $name = 'example-name',
        protected NonStruct $nonStruct = new NonStruct(),
        public \DateTimeInterface $createdAt = new \DateTimeImmutable('2025-01-01T00:00:00.000+00:00'),
    ) {
    }
}

/**
 * @internal
 */
class SerializableClass implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        protected int $value = 1,
        protected TestEntity $testEntity = new TestEntity()
    ) {
    }
}
