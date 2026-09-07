<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\PriceModifier\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification\OrderPriceModificationDefinition;
use Shopware\Core\Checkout\PriceModifier\Order\OrderPriceModificationTaxLockValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TaxRuleCollectionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Doctrine\FakeConnection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderPriceModificationTaxLockValidator::class)]
class OrderPriceModificationTaxLockValidatorTest extends TestCase
{
    private WriteContext $context;

    private OrderPriceModificationDefinition $definition;

    protected function setUp(): void
    {
        $this->context = WriteContext::createFromContext(Context::createDefaultContext());

        $registry = new StaticDefinitionInstanceRegistry(
            [OrderPriceModificationDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $definition = $registry->get(OrderPriceModificationDefinition::class);
        static::assertInstanceOf(OrderPriceModificationDefinition::class, $definition);
        $this->definition = $definition;
    }

    public function testSubscribedEvents(): void
    {
        $events = OrderPriceModificationTaxLockValidator::getSubscribedEvents();

        static::assertCount(1, $events);
        static::assertSame('preValidate', $events[PreWriteValidationEvent::class]);
    }

    public function testInsertIsNeverChecked(): void
    {
        $id = Uuid::randomBytes();

        $command = new InsertCommand(
            $this->definition,
            ['tax_rules' => '[]'],
            ['id' => $id, 'version_id' => Uuid::randomBytes()],
            EntityExistence::createForEntity('order_price_modification', ['id' => $id]),
            '/0/'
        );

        // Deliberately empty: even if a row happened to already exist at this id, an INSERT is never
        // checked against it -- confirms the guard is unconditional, not merely "no rows found".
        $this->assertNoViolation([$command], []);
    }

    public function testUpdateNotTouchingTaxRulesIsIgnored(): void
    {
        [$id, $versionId] = $this->randomPrimaryKey();

        $command = $this->updateCommand($id, $versionId, ['label' => 'Renamed']);

        $this->assertNoViolation([$command], [$this->persistedRow($id, $versionId, 'promotion', '[{"taxRate":19}]')]);
    }

    public function testUpdateWithIncompletePrimaryKeyIsIgnored(): void
    {
        $id = Uuid::randomBytes();

        $command = new UpdateCommand(
            $this->definition,
            ['tax_rules' => '[{"taxRate":7}]'],
            ['id' => $id],
            EntityExistence::createForEntity('order_price_modification', ['id' => $id]),
            '/0/'
        );

        $this->assertNoViolation([$command], []);
    }

    public function testRowNotFoundIsNeverLocked(): void
    {
        [$id, $versionId] = $this->randomPrimaryKey();

        $command = $this->updateCommand($id, $versionId, ['tax_rules' => '[{"taxRate":7}]']);

        $this->assertNoViolation([$command], []);
    }

    public function testManuallyAddedRowWithNullTypeIsFreelyEditable(): void
    {
        [$id, $versionId] = $this->randomPrimaryKey();

        $command = $this->updateCommand($id, $versionId, ['tax_rules' => '[{"taxRate":7}]']);

        $this->assertNoViolation([$command], [$this->persistedRow($id, $versionId, null, '[{"taxRate":19}]')]);
    }

    public function testChangingPersistedTaxRulesOnASystemContributedRowIsLocked(): void
    {
        [$id, $versionId] = $this->randomPrimaryKey();

        $command = $this->updateCommand($id, $versionId, ['tax_rules' => '[{"taxRate":7,"percentage":100}]']);

        $this->assertViolation(
            [$command],
            [$this->persistedRow($id, $versionId, 'promotion', '[{"taxRate":19,"percentage":100}]')],
            bin2hex($id)
        );
    }

    public function testRewritingTheSamePersistedTaxRulesIsNotAViolation(): void
    {
        [$id, $versionId] = $this->randomPrimaryKey();
        $taxRules = '[{"taxRate":19,"percentage":100}]';

        // Exactly what OrderPriceModificationProcessor::toPriceModifier() round-trips on every
        // system-side rewrite of a type-tagged row -- must never trip the lock.
        $command = $this->updateCommand($id, $versionId, ['tax_rules' => $taxRules]);

        $this->assertNoViolation([$command], [$this->persistedRow($id, $versionId, 'promotion', $taxRules)]);
    }

    /**
     * A native MySQL JSON column reformats whatever gets stored (adds whitespace, doesn't guarantee
     * key order) -- so the persisted value read back via SELECT is never byte-identical to a fresh
     * json_encode() of the same semantic value. A raw string comparison would wrongly treat this as
     * a change; the real fix is comparing decoded content, which this test proves directly without
     * needing a real database (see the integration test for the real-MySQL-column reproduction).
     */
    public function testDifferentlyFormattedButSemanticallyIdenticalTaxRulesIsNotAViolation(): void
    {
        [$id, $versionId] = $this->randomPrimaryKey();

        $command = $this->updateCommand($id, $versionId, ['tax_rules' => '[{"percentage": 100.0, "taxRate": 19.0}]']);

        $this->assertNoViolation([$command], [$this->persistedRow($id, $versionId, 'promotion', '[{"taxRate":19,"percentage":100}]')]);
    }

    /**
     * A genuine change must still be caught even though both values are reformatted/reordered --
     * proves the fix doesn't accidentally become too lenient.
     */
    public function testDifferentlyFormattedAndGenuinelyDifferentTaxRulesIsStillAViolation(): void
    {
        [$id, $versionId] = $this->randomPrimaryKey();

        $command = $this->updateCommand($id, $versionId, ['tax_rules' => '[{"percentage": 100.0, "taxRate": 7.0}]']);

        $this->assertViolation(
            [$command],
            [$this->persistedRow($id, $versionId, 'promotion', '[{"taxRate":19,"percentage":100}]')],
            bin2hex($id)
        );
    }

    /**
     * NULL (tax-exempt) and an empty collection (taxable, unrestricted across every rate) both decode
     * to "no rates" -- the lock must still tell them apart, it's the strongest change this field has.
     */
    #[DataProvider('taxTreatmentSwitchProvider')]
    public function testSwitchingBetweenTaxExemptAndUnrestrictedTaxableIsLocked(?string $persistedTaxRules, ?string $newTaxRules): void
    {
        [$id, $versionId] = $this->randomPrimaryKey();

        $command = $this->updateCommand($id, $versionId, ['tax_rules' => $newTaxRules]);

        $this->assertViolation([$command], [$this->persistedRow($id, $versionId, 'promotion', $persistedTaxRules)], bin2hex($id));
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function taxTreatmentSwitchProvider(): iterable
    {
        yield 'tax-exempt (NULL) becomes taxable across every rate ([])' => [null, '[]'];
        yield 'taxable across every rate ([]) becomes tax-exempt (NULL)' => ['[]', null];
    }

    #[DataProvider('unchangedTaxTreatmentProvider')]
    public function testResendingTheSameTaxExemptOrUnrestrictedTreatmentIsNotAViolation(?string $taxRules): void
    {
        [$id, $versionId] = $this->randomPrimaryKey();

        $command = $this->updateCommand($id, $versionId, ['tax_rules' => $taxRules]);

        $this->assertNoViolation([$command], [$this->persistedRow($id, $versionId, 'promotion', $taxRules)]);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function unchangedTaxTreatmentProvider(): iterable
    {
        yield 'tax-exempt (NULL) stays tax-exempt' => [null];
        yield 'taxable across every rate ([]) stays unrestricted' => ['[]'];
    }

    public function testClearingTheTypeOfASystemContributedRowIsLocked(): void
    {
        [$id, $versionId] = $this->randomPrimaryKey();

        // First half of the two-write bypass: without this lock, a follow-up write would find
        // `type` NULL and treat the row as manually added, leaving its tax_rules unprotected.
        $command = $this->updateCommand($id, $versionId, ['type' => null]);

        $this->assertViolations(
            [$command],
            [$this->persistedRow($id, $versionId, 'promotion', '[{"taxRate":19,"percentage":100}]')],
            [[bin2hex($id), OrderPriceModificationTaxLockValidator::VIOLATION_TYPE_LOCKED]]
        );
    }

    public function testClearingTheTypeWhileChangingTaxRulesReportsBothLocks(): void
    {
        [$id, $versionId] = $this->randomPrimaryKey();

        $command = $this->updateCommand($id, $versionId, ['type' => null, 'tax_rules' => '[{"taxRate":7,"percentage":100}]']);

        $this->assertViolations(
            [$command],
            [$this->persistedRow($id, $versionId, 'promotion', '[{"taxRate":19,"percentage":100}]')],
            [
                [bin2hex($id), OrderPriceModificationTaxLockValidator::VIOLATION_TYPE_LOCKED],
                [bin2hex($id), OrderPriceModificationTaxLockValidator::VIOLATION_TAX_LOCKED],
            ]
        );
    }

    public function testSettingATypeOnAManuallyAddedRowIsLocked(): void
    {
        [$id, $versionId] = $this->randomPrimaryKey();

        $command = $this->updateCommand($id, $versionId, ['type' => 'promotion']);

        $this->assertViolations(
            [$command],
            [$this->persistedRow($id, $versionId, null, '[{"taxRate":19,"percentage":100}]')],
            [[bin2hex($id), OrderPriceModificationTaxLockValidator::VIOLATION_TYPE_LOCKED]]
        );
    }

    public function testResendingTheUnchangedTypeIsNotAViolation(): void
    {
        [$id, $versionId] = $this->randomPrimaryKey();

        $command = $this->updateCommand($id, $versionId, ['type' => 'promotion', 'label' => 'Renamed']);

        $this->assertNoViolation([$command], [$this->persistedRow($id, $versionId, 'promotion', '[{"taxRate":19,"percentage":100}]')]);
    }

    /**
     * @param list<InsertCommand|UpdateCommand> $commands
     * @param list<array<string, mixed>> $dbRows
     */
    private function assertNoViolation(array $commands, array $dbRows): void
    {
        $exception = $this->runValidator($commands, $dbRows);

        static::assertNull($exception);
    }

    /**
     * @param list<InsertCommand|UpdateCommand> $commands
     * @param list<array<string, mixed>> $dbRows
     */
    private function assertViolation(array $commands, array $dbRows, string $expectedId): void
    {
        $this->assertViolations($commands, $dbRows, [[$expectedId, OrderPriceModificationTaxLockValidator::VIOLATION_TAX_LOCKED]]);
    }

    /**
     * @param list<InsertCommand|UpdateCommand> $commands
     * @param list<array<string, mixed>> $dbRows
     * @param list<array{0: string, 1: string}> $expected id and violation code, in reporting order
     */
    private function assertViolations(array $commands, array $dbRows, array $expected): void
    {
        $exception = $this->runValidator($commands, $dbRows);

        static::assertNotNull($exception);
        static::assertCount(1, $exception->getExceptions());
        $violationException = $exception->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $violationException);

        $actual = [];
        foreach ($violationException->getViolations() as $violation) {
            static::assertStringContainsString((string) $violation->getParameters()['{{ id }}'], (string) $violation->getMessage());
            $actual[] = [$violation->getParameters()['{{ id }}'], $violation->getCode()];
        }

        static::assertSame($expected, $actual);
    }

    /**
     * @param list<InsertCommand|UpdateCommand> $commands
     * @param list<array<string, mixed>> $dbRows
     */
    private function runValidator(array $commands, array $dbRows): ?WriteException
    {
        $fakeConnection = new FakeConnection($dbRows);
        $event = new PreWriteValidationEvent($this->context, $commands);
        $serializer = new TaxRuleCollectionFieldSerializer(
            static::createStub(ValidatorInterface::class),
            static::createStub(DefinitionInstanceRegistry::class)
        );
        $validator = new OrderPriceModificationTaxLockValidator($fakeConnection, $serializer);
        $validator->preValidate($event);

        try {
            $event->getExceptions()->tryToThrow();
        } catch (WriteException $e) {
            return $e;
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function randomPrimaryKey(): array
    {
        return [Uuid::randomBytes(), Uuid::randomBytes()];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function updateCommand(string $id, string $versionId, array $payload): UpdateCommand
    {
        return new UpdateCommand(
            $this->definition,
            $payload,
            ['id' => $id, 'version_id' => $versionId],
            EntityExistence::createForEntity('order_price_modification', ['id' => $id, 'version_id' => $versionId]),
            '/0/'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function persistedRow(string $id, string $versionId, ?string $type, ?string $taxRules): array
    {
        return [
            'id' => bin2hex($id),
            'version_id' => bin2hex($versionId),
            'type' => $type,
            'tax_rules' => $taxRules,
        ];
    }
}
