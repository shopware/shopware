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

        static::assertTrue($row->isInPayload('stock'));
        static::assertFalse($row->isInPayload('active'));
        static::assertSame([2, '1'], [$row->getResultingValue('stock'), $row->getResultingValue('active')]);
    }

    public function testAColumnThatWasNotRequestedCannotBeReadEvenFromThePayload(): void
    {
        $row = $this->row(payload: ['stock' => 2, 'product_number' => 'p2'], state: ['stock' => '1']);

        $this->expectExceptionObject(DataAbstractionLayerException::outcomeRowColumnNotRequested('product_number'));

        $row->getResultingValue('product_number');
    }

    public function testAColumnThatWasNotRequestedCannotBeCheckedInThePayload(): void
    {
        $row = $this->row(payload: ['stock' => 2, 'product_number' => 'p2'], state: ['stock' => '1']);

        $this->expectExceptionObject(DataAbstractionLayerException::outcomeRowColumnNotRequested('product_number'));

        $row->isInPayload('product_number');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $state
     */
    private function row(array $payload, array $state): OutcomeRow
    {
        return new OutcomeRow(
            primaryKey: ['id' => 'a'],
            path: '',
            isNew: false,
            payload: $payload,
            state: $state,
        );
    }
}
