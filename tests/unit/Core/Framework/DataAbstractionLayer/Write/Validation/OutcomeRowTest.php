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

        static::assertTrue($row->hasField('stock'));
        static::assertFalse($row->hasField('active'));
        static::assertSame([2, '1'], [$row->get('stock'), $row->get('active')]);
    }

    public function testAColumnThatWasNotLoadedCannotBeRead(): void
    {
        $row = $this->row(payload: ['stock' => 2], state: ['stock' => '1']);

        $this->expectExceptionObject(DataAbstractionLayerException::outcomeRowColumnNotLoaded('active'));

        $row->get('active');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $state
     */
    private function row(array $payload, array $state): OutcomeRow
    {
        return new OutcomeRow(
            primaryKey: ['id' => 'a'],
            decodedPrimaryKey: ['id' => 'a'],
            path: '',
            isNew: false,
            payload: $payload,
            state: $state,
        );
    }
}
