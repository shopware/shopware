<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Unit;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\Unit\UnitCollection;
use Shopware\Core\System\Unit\UnitValidator;

/**
 * @internal
 */
#[Package('inventory')]
class UnitUneceCodeTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<UnitCollection>
     */
    private EntityRepository $unitRepository;

    protected function setUp(): void
    {
        $this->unitRepository = static::getContainer()->get('unit.repository');
    }

    public function testSavesAndReadsListedCode(): void
    {
        $id = $this->createUnit('XBX');

        static::assertSame('XBX', $this->readUneceCode($id));
    }

    public function testEmptyCodeIsStoredAsNull(): void
    {
        $id = $this->createUnit('');

        static::assertNull($this->readUneceCode($id));
    }

    public function testRejectsUnlistedCode(): void
    {
        $exception = null;

        try {
            $this->createUnit('kgm');
        } catch (WriteException $exception) {
        }

        static::assertInstanceOf(WriteException::class, $exception);
        $inner = $exception->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $inner);
        $violation = $inner->getViolations()->get(0);
        static::assertSame('/uneceCode', $violation->getPropertyPath());
        static::assertSame(UnitValidator::VIOLATION_INVALID_UNECE_CODE, $violation->getCode());
    }

    public function testRejectsUnlistedCodeOnUpdate(): void
    {
        $id = $this->createUnit('KGM');

        $this->expectException(WriteException::class);

        $this->unitRepository->update([['id' => $id, 'uneceCode' => 'ZZZ']], Context::createDefaultContext());
    }

    private function createUnit(string $uneceCode): string
    {
        $id = Uuid::randomHex();

        $this->unitRepository->create([[
            'id' => $id,
            'shortCode' => 'u',
            'name' => 'unit',
            'uneceCode' => $uneceCode,
        ]], Context::createDefaultContext());

        return $id;
    }

    private function readUneceCode(string $id): ?string
    {
        $unit = $this->unitRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($unit);

        return $unit->getUneceCode();
    }
}
