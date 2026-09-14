<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\NumberRange\Validation;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeCollection;
use Shopware\Core\System\NumberRange\NumberRangeCollection;

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

    private Logger $logger;

    protected function setUp(): void
    {
        $this->numberRangeTypeRepository = static::getContainer()->get('number_range_type.repository');
        $this->numberRangeRepository = static::getContainer()->get('number_range.repository');
        $this->context = Context::createDefaultContext();
        $this->logger = static::getContainer()->get('logger');
    }

    public function testRepositoryCreateLogsWarningForCollidingPatternButSucceeds(): void
    {
        $typeId = $this->createDocumentNumberRangeType('document_test_invoice');
        $this->createNumberRange($typeId, 'INV{n}');

        // create() itself would throw if the write were rejected, so reaching
        // the warning assertion below already proves the write went through.
        $this->expectPatternCollisionWarning(fn () => $this->createNumberRange($typeId, 'INV{n}'));
    }

    public function testRepositoryCreateAllowsDistinctPatternForSameDocumentType(): void
    {
        $typeId = $this->createDocumentNumberRangeType('document_test_delivery_note');
        $this->createNumberRange($typeId, 'DEL{n}');

        $this->createNumberRange($typeId, 'DEL-B-{n}');

        static::addToAssertionCount(1);
    }

    public function testRepositoryUpdateLogsWarningForCollidingPattern(): void
    {
        $typeId = $this->createDocumentNumberRangeType('document_test_credit_note');
        $this->createNumberRange($typeId, 'CN{n}');
        $secondId = $this->createNumberRange($typeId, 'CN-B-{n}');

        $this->expectPatternCollisionWarning(fn () => $this->numberRangeRepository->update([[
            'id' => $secondId,
            'pattern' => 'CN{n}',
        ]], $this->context));
    }

    public function testRepositoryCreateLogsWarningForCollidingPatternInSameWriteBatch(): void
    {
        $typeId = $this->createDocumentNumberRangeType('document_test_storno');

        $this->expectPatternCollisionWarning(fn () => $this->numberRangeRepository->create([
            $this->numberRangePayload($typeId, 'STO{n}'),
            $this->numberRangePayload($typeId, 'STO{n}'),
        ], $this->context));
    }

    public function testRepositoryCreateAllowsCollidingPatternForNonDocumentType(): void
    {
        $typeId = $this->createNumberRangeType('test_non_document_type');
        $this->createNumberRange($typeId, 'X{n}');

        $handler = new TestHandler(Level::Warning);
        $this->logger->pushHandler($handler);

        try {
            $this->createNumberRange($typeId, 'X{n}');

            static::assertEmpty($handler->getRecords());
        } finally {
            $this->logger->popHandler();
        }
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

    /**
     * Runs the callback and asserts it logged a pattern-collision warning
     * instead of throwing — the write is expected to succeed regardless.
     */
    private function expectPatternCollisionWarning(\Closure $callback): void
    {
        $handler = new TestHandler(Level::Warning);
        $this->logger->pushHandler($handler);

        try {
            $callback();

            static::assertNotEmpty($handler->getRecords());
            static::assertStringContainsString('already used by another', $handler->getRecords()[0]->message);
        } finally {
            $this->logger->popHandler();
        }
    }
}
