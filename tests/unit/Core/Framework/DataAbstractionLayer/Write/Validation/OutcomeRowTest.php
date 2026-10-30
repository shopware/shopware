<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OutcomeRow::class)]
class OutcomeRowTest extends TestCase
{
    public function testTheValueFromThePayloadTakesPrecedenceOverTheState(): void
    {
        $row = $this->row(payload: ['stock' => 2], state: ['stock' => '1', 'active' => '1']);

        static::assertSame([2, '1'], [$row->getResultingValue('stock'), $row->getResultingValue('active')]);
    }

    public function testANullInThePayloadTakesPrecedenceOverTheState(): void
    {
        $row = $this->row(payload: ['stock' => null], state: ['stock' => '1']);

        static::assertNull($row->getResultingValue('stock'));
    }

    public function testThePayloadContainsAnyOfTheColumnsWhenTheWriteSetsOneOfThem(): void
    {
        $row = $this->row(payload: ['stock' => 2, 'product_number' => 'p2'], state: ['stock' => '1', 'active' => '1']);

        static::assertTrue($row->payloadContainsAny('active', 'stock'));
        static::assertTrue($row->payloadContainsAny('product_number'));
        static::assertFalse($row->payloadContainsAny('active'));
    }

    public function testThePayloadValueIsOnlyWhatTheWriteSets(): void
    {
        $row = $this->row(payload: ['stock' => 2], state: ['stock' => '1', 'active' => '1']);

        static::assertSame([2, null], [$row->getPayloadValue('stock'), $row->getPayloadValue('active')]);
    }

    public function testAColumnThatWasNotRequestedCannotBeReadEvenFromThePayload(): void
    {
        $row = $this->row(payload: ['stock' => 2, 'product_number' => 'p2'], state: ['stock' => '1']);

        $this->expectExceptionObject(DataAbstractionLayerException::outcomeRowColumnNotRequested('product_number'));

        $row->getResultingValue('product_number');
    }

    public function testAColumnThatWasNotRequestedCannotBeReadFromThePayload(): void
    {
        $row = $this->row(payload: ['stock' => 2, 'product_number' => 'p2'], state: ['stock' => '1']);

        $this->expectExceptionObject(DataAbstractionLayerException::outcomeRowColumnNotRequested('product_number'));

        $row->getPayloadValue('product_number');
    }

    public function testThePayloadContainsOnlyTheColumnsTheWriteSets(): void
    {
        $row = $this->row(payload: ['active' => 1, 'updated_at' => 'now'], state: ['stock' => '1']);

        static::assertTrue($row->payloadContainsOnly('active', 'updated_at'));
        static::assertFalse($row->payloadContainsOnly('active'));
    }

    public function testTheWriteOfADeletedRecordContainsMoreThanColumns(): void
    {
        $row = $this->row(payload: [], state: ['stock' => '1'], isDeleted: true);

        static::assertFalse($row->payloadContainsOnly('active', 'updated_at'));
    }

    public function testTheStoredValueIsTheValueBeforeTheWrite(): void
    {
        $row = $this->row(payload: ['stock' => 2], state: ['stock' => '1']);

        static::assertSame('1', $row->getStoredValue('stock'));
    }

    public function testADeletedRecordHasNoValueAfterTheWrite(): void
    {
        $row = $this->row(payload: [], state: ['stock' => '1'], isDeleted: true);

        static::assertNull($row->getResultingValue('stock'));
    }

    public function testTheResultingArrayHoldsThePrimaryKeyAndTheResultingValueOfEachRequestedColumn(): void
    {
        $row = $this->row(payload: ['stock' => 2, 'product_number' => 'p2'], state: ['stock' => '1', 'active' => '1']);

        static::assertSame(['id' => 'a', 'stock' => 2, 'active' => '1'], $row->toResultingArray());
    }

    public function testTheStoredArrayHoldsThePrimaryKeyAndTheStoredValueOfEachRequestedColumn(): void
    {
        $row = $this->row(payload: ['stock' => 2], state: ['stock' => '1', 'active' => '1'], isDeleted: true);

        static::assertSame(['id' => 'a', 'stock' => '1', 'active' => '1'], $row->toStoredArray());
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $state
     */
    private function row(array $payload, array $state, bool $isDeleted = false): OutcomeRow
    {
        return new OutcomeRow(
            primaryKey: ['id' => 'a'],
            path: '',
            isNew: false,
            isDeleted: $isDeleted,
            payload: $payload,
            state: $state,
        );
    }
}
