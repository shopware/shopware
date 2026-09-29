<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier\Order;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TaxRuleCollectionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TaxRuleCollectionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Blocks an API/Admin write from changing order_price_modification.tax_rules on a row the
 * checkout/cart pipeline itself contributed (non-NULL `type`) once it already exists -- its tax
 * treatment was already settled at creation, so changing it later would misstate VAT. A manually
 * admin-added row (`type` NULL) stays freely editable. Field-scoped, not row-locked: only
 * `tax_rules` is protected, everything else on the row may still change.
 *
 * `type` itself is the provenance marker the lock reads, so it is immutable on every existing row
 * -- otherwise a first write clearing it would make the row look manually added and let a second
 * write change `tax_rules` unchecked. Deleting a row stays allowed; a replacement then has to be
 * added as a new, visibly manual (`type` NULL) row.
 *
 * A no-op resend of `tax_rules` must not trip the lock, so values are compared semantically
 * (decoded rate/percentage pairs), not as raw JSON strings -- MySQL's native `JSON` column
 * reformats on storage, so a persisted value read back is essentially never byte-identical to a
 * freshly encoded one even when unchanged.
 */
#[Package('checkout')]
final class OrderPriceModificationTaxLockValidator implements EventSubscriberInterface
{
    final public const VIOLATION_TAX_LOCKED = 'CHECKOUT__ORDER_PRICE_MODIFICATION_TAX_LOCKED';

    final public const VIOLATION_TYPE_LOCKED = 'CHECKOUT__ORDER_PRICE_MODIFICATION_TYPE_LOCKED';

    private const MESSAGES = [
        self::VIOLATION_TAX_LOCKED => 'The tax treatment of order_price_modification %s can no longer be changed: it was created automatically by the checkout process, not added manually.',
        self::VIOLATION_TYPE_LOCKED => 'The type of order_price_modification %s can no longer be changed once it has been created.',
    ];

    private const ENTITY_NAME = 'order_price_modification';

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly TaxRuleCollectionFieldSerializer $taxRuleSerializer
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    /**
     * @throws WriteConstraintViolationException
     */
    public function preValidate(PreWriteValidationEvent $event): void
    {
        $lockedChanges = $this->findLockedChanges($event->getCommands());

        if ($lockedChanges === []) {
            return;
        }

        $violations = new ConstraintViolationList();

        foreach ($lockedChanges as [$id, $code]) {
            $violations->add(new ConstraintViolation(
                \sprintf(self::MESSAGES[$code], $id),
                \sprintf(self::MESSAGES[$code], '{{ id }}'),
                ['{{ id }}' => $id],
                null,
                '/',
                null,
                null,
                $code
            ));
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    /**
     * @param WriteCommand[] $writeCommands
     *
     * @return list<array{0: string, 1: self::VIOLATION_*}>
     */
    private function findLockedChanges(array $writeCommands): array
    {
        $candidates = [];

        foreach ($writeCommands as $command) {
            if ($command instanceof InsertCommand) {
                // A brand-new row is never locked yet -- nothing to compare against.
                continue;
            }

            if ($command->getEntityName() !== self::ENTITY_NAME || !$command->hasAnyField('tax_rules', 'type')) {
                continue;
            }

            $primaryKey = $command->getPrimaryKey();

            if (!isset($primaryKey['id'], $primaryKey['version_id'])) {
                continue;
            }

            $candidates[] = [
                'id' => $primaryKey['id'],
                'versionId' => $primaryKey['version_id'],
                'command' => $command,
            ];
        }

        if ($candidates === []) {
            return [];
        }

        $rows = $this->connection->createQueryBuilder()
            ->select('LOWER(HEX(id)) AS id', 'LOWER(HEX(version_id)) AS version_id', 'type', 'tax_rules')
            ->from(self::ENTITY_NAME)
            ->where('id IN (:ids)')
            ->setParameter('ids', array_column($candidates, 'id'), ArrayParameterType::BINARY)
            ->executeQuery()
            ->fetchAllAssociative();

        $persisted = [];
        foreach ($rows as $row) {
            $persisted[$row['id'] . $row['version_id']] = $row;
        }

        $lockedChanges = [];

        foreach ($candidates as $candidate) {
            $key = bin2hex($candidate['id']) . bin2hex($candidate['versionId']);
            $row = $persisted[$key] ?? null;

            if ($row === null) {
                // Not found (shouldn't happen for an UPDATE) -- nothing to protect.
                continue;
            }

            $command = $candidate['command'];
            $id = bin2hex($candidate['id']);

            if ($command->hasField('type') && $command->getPayload()['type'] !== $row['type']) {
                $lockedChanges[] = [$id, self::VIOLATION_TYPE_LOCKED];
            }

            if ($row['type'] === null || !$command->hasField('tax_rules')) {
                // A manually admin-added row -- its tax treatment stays freely editable.
                continue;
            }

            if (!$this->taxRulesAreSemanticEqual($row['tax_rules'], $command->getPayload()['tax_rules'])) {
                $lockedChanges[] = [$id, self::VIOLATION_TAX_LOCKED];
            }
        }

        return $lockedChanges;
    }

    /**
     * Compares two encoded tax_rules values by decoded content (rate + percentage pairs,
     * order-independent, float-epsilon-tolerant), never as raw strings -- see the class docblock for
     * why a raw comparison is unsafe (MySQL's native JSON column reformats on storage).
     */
    private function taxRulesAreSemanticEqual(?string $persisted, ?string $new): bool
    {
        $persistedRates = $this->decodeTaxRates($persisted);
        $newRates = $this->decodeTaxRates($new);

        // NULL (tax-exempt) and an empty collection (taxable, unrestricted) are the two most
        // different treatments this field has -- never let them collapse into "no rates" below.
        if ($persistedRates === null || $newRates === null) {
            return $persistedRates === $newRates;
        }

        if (\count($persistedRates) !== \count($newRates)) {
            return false;
        }

        $sort = static fn (array $a, array $b): int => $a['taxRate'] <=> $b['taxRate'] ?: $a['percentage'] <=> $b['percentage'];
        usort($persistedRates, $sort);
        usort($newRates, $sort);

        foreach ($persistedRates as $i => $rate) {
            if (!FloatComparator::equals($rate['taxRate'], $newRates[$i]['taxRate'])
                || !FloatComparator::equals($rate['percentage'], $newRates[$i]['percentage'])
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * NULL means tax-exempt, same as the column itself; an empty list means taxable, unrestricted.
     *
     * @return list<array{taxRate: float, percentage: float}>|null
     */
    private function decodeTaxRates(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }

        $collection = $this->taxRuleSerializer->decode(new TaxRuleCollectionField('tax_rules', 'taxRules'), $json);

        if (!$collection instanceof TaxRuleCollection) {
            return null;
        }

        return array_map(
            static fn (TaxRule $rule): array => ['taxRate' => $rule->getTaxRate(), 'percentage' => $rule->getPercentage()],
            array_values($collection->getElements())
        );
    }
}
