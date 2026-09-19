<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\NumberRange\Validation;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
        $typeId = $this->createNumberRangeType('document_test_invoice');
        $firstId = $this->createNumberRange($typeId, 'INV{n}');
        $secondId = Uuid::randomHex();

        $warnings = $this->collectCollisionWarnings(fn () => $this->numberRangeRepository->create(
            [$this->numberRangePayload($secondId, $typeId, 'INV{n}')],
            $this->context
        ));

        static::assertSame([[
            'numberRangeId' => $secondId,
            'pattern' => 'INV{n}',
            'documentType' => 'test_invoice',
        ]], $warnings);

        static::assertSame('INV{n}', $this->fetchPersistedPattern($firstId));
        static::assertSame('INV{n}', $this->fetchPersistedPattern($secondId));
    }

    public function testRepositoryCreateLogsNoWarningForDistinctPatternOfSameDocumentType(): void
    {
        $typeId = $this->createNumberRangeType('document_test_delivery_note');
        $this->createNumberRange($typeId, 'DEL{n}');
        $secondId = Uuid::randomHex();

        $warnings = $this->collectCollisionWarnings(fn () => $this->numberRangeRepository->create(
            [$this->numberRangePayload($secondId, $typeId, 'DEL-B-{n}')],
            $this->context
        ));

        static::assertSame([], $warnings);
        static::assertSame('DEL-B-{n}', $this->fetchPersistedPattern($secondId));
    }

    public function testRepositoryUpdateLogsWarningForCollidingPattern(): void
    {
        $typeId = $this->createNumberRangeType('document_test_credit_note');
        $this->createNumberRange($typeId, 'CN{n}');
        $secondId = $this->createNumberRange($typeId, 'CN-B-{n}');

        $warnings = $this->collectCollisionWarnings(fn () => $this->numberRangeRepository->update([[
            'id' => $secondId,
            'pattern' => 'CN{n}',
        ]], $this->context));

        static::assertSame([[
            'numberRangeId' => $secondId,
            'pattern' => 'CN{n}',
            'documentType' => 'test_credit_note',
        ]], $warnings);

        static::assertSame('CN{n}', $this->fetchPersistedPattern($secondId));
    }

    public function testRepositoryCreateLogsWarningForCollidingPatternInSameWriteBatch(): void
    {
        $typeId = $this->createNumberRangeType('document_test_storno');
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();

        $warnings = $this->collectCollisionWarnings(fn () => $this->numberRangeRepository->create([
            $this->numberRangePayload($firstId, $typeId, 'STO{n}'),
            $this->numberRangePayload($secondId, $typeId, 'STO{n}'),
        ], $this->context));

        static::assertCount(2, $warnings);
        static::assertEqualsCanonicalizing([$firstId, $secondId], \array_column($warnings, 'numberRangeId'));
        static::assertSame(['STO{n}', 'STO{n}'], \array_column($warnings, 'pattern'));
        static::assertSame(['test_storno', 'test_storno'], \array_column($warnings, 'documentType'));

        static::assertSame('STO{n}', $this->fetchPersistedPattern($firstId));
        static::assertSame('STO{n}', $this->fetchPersistedPattern($secondId));
    }

    public function testRepositoryCreateLogsNoWarningForCollidingPatternOfNonDocumentType(): void
    {
        $typeId = $this->createNumberRangeType('test_non_document_type');
        $this->createNumberRange($typeId, 'X{n}');
        $secondId = Uuid::randomHex();

        $warnings = $this->collectCollisionWarnings(fn () => $this->numberRangeRepository->create(
            [$this->numberRangePayload($secondId, $typeId, 'X{n}')],
            $this->context
        ));

        static::assertSame([], $warnings);
        static::assertSame('X{n}', $this->fetchPersistedPattern($secondId));
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
        $id = Uuid::randomHex();

        $this->numberRangeRepository->create([$this->numberRangePayload($id, $typeId, $pattern)], $this->context);

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function numberRangePayload(string $id, string $typeId, string $pattern): array
    {
        return [
            'id' => $id,
            'typeId' => $typeId,
            'global' => false,
            'pattern' => $pattern,
            'start' => 1000,
            'name' => 'Test number range',
        ];
    }

    private function fetchPersistedPattern(string $numberRangeId): ?string
    {
        $numberRange = $this->numberRangeRepository
            ->search(new Criteria([$numberRangeId]), $this->context)
            ->getEntities()
            ->get($numberRangeId);

        static::assertNotNull($numberRange);

        return $numberRange->getPattern();
    }

    /**
     * Runs the callback and returns the context of every pattern-collision warning it logged,
     * so callers can assert on the resolved number range, pattern and document type.
     *
     * @return list<array<string, mixed>>
     */
    private function collectCollisionWarnings(\Closure $callback): array
    {
        $handler = new TestHandler(Level::Warning);
        $this->logger->pushHandler($handler);

        try {
            $callback();
        } finally {
            $this->logger->popHandler();
        }

        $warnings = [];
        foreach ($handler->getRecords() as $record) {
            if (!\str_contains($record->message, 'Generated documents may collide')) {
                continue;
            }

            $warnings[] = [
                'numberRangeId' => $record->context['numberRangeId'] ?? null,
                'pattern' => $record->context['pattern'] ?? null,
                'documentType' => $record->context['documentType'] ?? null,
            ];
        }

        return $warnings;
    }
}
