<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\NumberRange\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeCollection;
use Shopware\Core\System\NumberRange\NumberRangeCollection;
use Shopware\Core\System\NumberRange\Validation\NumberRangePatternCollisionValidator;

/**
 * @internal
 */
#[Package('framework')]
class NumberRangePatternCollisionValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<NumberRangeTypeCollection>
     */
    private EntityRepository $numberRangeTypeRepository;

    /**
     * @var EntityRepository<NumberRangeCollection>
     */
    private EntityRepository $numberRangeRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->numberRangeTypeRepository = static::getContainer()->get('number_range_type.repository');
        $this->numberRangeRepository = static::getContainer()->get('number_range.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testRepositoryCreateRejectsCollidingPatternForSameDocumentType(): void
    {
        $typeId = $this->createDocumentNumberRangeType('document_test_invoice');
        $this->createNumberRange($typeId, 'INV{n}');

        $this->expectPatternCollisionViolation(fn () => $this->createNumberRange($typeId, 'INV{n}'));
    }

    public function testRepositoryCreateAllowsDistinctPatternForSameDocumentType(): void
    {
        $typeId = $this->createDocumentNumberRangeType('document_test_delivery_note');
        $this->createNumberRange($typeId, 'DEL{n}');

        $this->createNumberRange($typeId, 'DEL-B-{n}');

        static::addToAssertionCount(1);
    }

    public function testRepositoryUpdateRejectsCollidingPattern(): void
    {
        $typeId = $this->createDocumentNumberRangeType('document_test_credit_note');
        $this->createNumberRange($typeId, 'CN{n}');
        $secondId = $this->createNumberRange($typeId, 'CN-B-{n}');

        $this->expectPatternCollisionViolation(fn () => $this->numberRangeRepository->update([[
            'id' => $secondId,
            'pattern' => 'CN{n}',
        ]], $this->context));
    }

    public function testRepositoryCreateRejectsCollidingPatternInSameWriteBatch(): void
    {
        $typeId = $this->createDocumentNumberRangeType('document_test_storno');

        $this->expectPatternCollisionViolation(fn () => $this->numberRangeRepository->create([
            $this->numberRangePayload($typeId, 'STO{n}'),
            $this->numberRangePayload($typeId, 'STO{n}'),
        ], $this->context));
    }

    public function testRepositoryCreateAllowsCollidingPatternForNonDocumentType(): void
    {
        $typeId = $this->createNumberRangeType('test_non_document_type');
        $this->createNumberRange($typeId, 'X{n}');

        $this->createNumberRange($typeId, 'X{n}');

        static::addToAssertionCount(1);
    }

    private function createDocumentNumberRangeType(string $technicalName): string
    {
        return $this->createNumberRangeType($technicalName);
    }

    private function createNumberRangeType(string $technicalName): string
    {
        $id = Uuid::randomHex();

        $this->numberRangeTypeRepository->create([[
            'id' => $id,
            'technicalName' => $technicalName,
            'global' => false,
            'typeName' => $technicalName,
        ]], $this->context);

        return $id;
    }

    private function createNumberRange(string $typeId, string $pattern): string
    {
        $payload = $this->numberRangePayload($typeId, $pattern);

        $this->numberRangeRepository->create([$payload], $this->context);

        return $payload['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function numberRangePayload(string $typeId, string $pattern): array
    {
        return [
            'id' => Uuid::randomHex(),
            'typeId' => $typeId,
            'global' => false,
            'pattern' => $pattern,
            'start' => 1000,
            'name' => 'Test number range',
        ];
    }

    private function expectPatternCollisionViolation(\Closure $callback): void
    {
        try {
            $callback();
            static::fail('Expected a number range pattern collision violation.');
        } catch (WriteException $exception) {
            $writeException = $exception->getExceptions()[0] ?? null;

            static::assertInstanceOf(WriteConstraintViolationException::class, $writeException);
            static::assertSame(
                NumberRangePatternCollisionValidator::NUMBER_RANGE_PATTERN_NOT_UNIQUE,
                $writeException->getViolations()->get(0)->getCode(),
            );
        }
    }
}
