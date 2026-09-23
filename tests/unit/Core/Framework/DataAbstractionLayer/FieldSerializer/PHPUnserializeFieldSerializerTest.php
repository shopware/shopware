<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PHPUnserializeFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Assert\Serialization;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PHPUnserializeFieldSerializer::class)]
class PHPUnserializeFieldSerializerTest extends TestCase
{
    private PHPUnserializeFieldSerializer $serializer;

    private Field $field;

    protected function setUp(): void
    {
        $this->serializer = new PHPUnserializeFieldSerializer();
        $this->field = new CheapestPriceField('cheapest_price', 'cheapestPrice');
    }

    public function testEncodeIsNotSupportedBecauseTheFieldIsWrittenByTheIndexer(): void
    {
        $this->expectExceptionObject(DataAbstractionLayerException::serializedFieldRequiresIndexer());

        $generator = $this->serializer->encode(
            $this->field,
            EntityExistence::createEmpty(),
            new KeyValuePair('cheapestPrice', 'value', true),
            static::createStub(WriteParameterBag::class),
        );

        // the generator body only runs once it is iterated
        iterator_to_array($generator);
    }

    public function testNullIsDecodedToNull(): void
    {
        static::assertNull($this->serializer->decode($this->field, null));
    }

    public function testSmallPayloadIsUnserializedOnEveryDecode(): void
    {
        $payload = serialize($this->createContainerLikeObject(1));
        static::assertLessThan(PHPUnserializeFieldSerializer::MEMOIZE_MIN_BYTES, \strlen($payload));

        $first = $this->serializer->decode($this->field, $payload);
        $second = $this->serializer->decode($this->field, $payload);

        static::assertEquals($first, $second);
        static::assertNotSame($first, $second);
    }

    public function testIdenticalLargePayloadIsUnserializedOnceAndSharedBetweenRows(): void
    {
        $payload = $this->createLargePayload('family-a');

        $firstRow = $this->serializer->decode($this->field, $payload);
        $secondRow = $this->serializer->decode($this->field, $payload);

        static::assertIsObject($firstRow);
        static::assertSame($firstRow, $secondRow);
        Serialization::assertUnserializedEquals($firstRow, $payload);
    }

    public function testLargePayloadsWithDifferentContentAreDecodedSeparately(): void
    {
        $familyA = $this->serializer->decode($this->field, $this->createLargePayload('family-a'));
        $familyB = $this->serializer->decode($this->field, $this->createLargePayload('family-b'));

        static::assertIsObject($familyA);
        static::assertIsObject($familyB);
        static::assertNotSame($familyA, $familyB);
        static::assertNotEquals($familyA, $familyB);
    }

    public function testResetForgetsSharedPayloads(): void
    {
        $payload = $this->createLargePayload('family-a');
        $beforeReset = $this->serializer->decode($this->field, $payload);

        $this->serializer->reset();
        $afterReset = $this->serializer->decode($this->field, $payload);

        static::assertEquals($beforeReset, $afterReset);
        static::assertNotSame($beforeReset, $afterReset);
    }

    public function testOldestPayloadIsDroppedWhenTheMemoIsFull(): void
    {
        $oldest = $this->createLargePayload('family-0');
        $oldestDecoded = $this->serializer->decode($this->field, $oldest);

        for ($i = 1; $i <= PHPUnserializeFieldSerializer::MEMOIZE_MAX_ENTRIES; ++$i) {
            $this->serializer->decode($this->field, $this->createLargePayload('family-' . $i));
        }

        $newest = $this->createLargePayload('family-' . PHPUnserializeFieldSerializer::MEMOIZE_MAX_ENTRIES);
        static::assertSame(
            $this->serializer->decode($this->field, $newest),
            $this->serializer->decode($this->field, $newest),
            'the most recent payload must still be shared',
        );

        static::assertNotSame(
            $oldestDecoded,
            $this->serializer->decode($this->field, $oldest),
            'the oldest payload must have been dropped to keep the memo bounded',
        );
    }

    /**
     * Mirrors the shape of a cheapest price container: one entry per variant, each entry a
     * handful of scalar columns. Only the size matters for the serializer, not the price semantics.
     */
    private function createContainerLikeObject(int $variants, string $family = 'family'): \stdClass
    {
        $entries = [];
        for ($i = 0; $i < $variants; ++$i) {
            $entries[$family . '-variant-' . $i] = [
                'default' => [
                    'parent_id' => $family,
                    'variant_id' => $family . '-variant-' . $i,
                    'price' => ['gross' => 10.0 + $i, 'net' => 8.4 + $i],
                    'is_ranged' => false,
                ],
            ];
        }

        $container = new \stdClass();
        $container->value = $entries;

        return $container;
    }

    private function createLargePayload(string $family): string
    {
        $payload = serialize($this->createContainerLikeObject(1000, $family));
        static::assertGreaterThanOrEqual(PHPUnserializeFieldSerializer::MEMOIZE_MIN_BYTES, \strlen($payload));

        return $payload;
    }
}
