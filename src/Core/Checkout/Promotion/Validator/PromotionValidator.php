<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Validator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Checkout\Promotion\Validator\PromotionValidatorTest
 */
#[Package('checkout')]
class PromotionValidator implements EventSubscriberInterface
{
    /**
     * this is the min value for all types
     * (absolute, percentage, ...)
     */
    private const DISCOUNT_MIN_VALUE = 0.00;

    /**
     * this is used for the maximum allowed
     * percentage discount.
     */
    private const DISCOUNT_PERCENTAGE_MAX_VALUE = 100.0;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly WriteOutcome $writeOutcome,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    /**
     * This function validates promotions and their discounts as they will be after the write.
     * It does only check for business relevant rules and logic.
     * All primitive "required" constraints are done inside the definition of the entity.
     */
    public function preValidate(PreWriteValidationEvent $event): void
    {
        $promotions = $this->writeOutcome->resultingRows(
            $event->getCommandsForEntity(PromotionDefinition::ENTITY_NAME),
            ['valid_from', 'valid_until', 'use_codes', 'use_individual_codes', 'individual_code_pattern', 'code']
        );

        foreach ($promotions as $promotion) {
            $this->addViolations($event, $promotion, $this->validatePromotion($promotion));
        }

        $discounts = $this->writeOutcome->resultingRows(
            $event->getCommandsForEntity(PromotionDiscountDefinition::ENTITY_NAME),
            ['type', 'value']
        );

        foreach ($discounts as $discount) {
            $this->addViolations($event, $discount, $this->validateDiscount($discount));
        }
    }

    private function addViolations(PreWriteValidationEvent $event, OutcomeRow $row, ConstraintViolationList $violationList): void
    {
        if ($violationList->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violationList, $row->path));
        }
    }

    private function validatePromotion(OutcomeRow $promotion): ConstraintViolationList
    {
        $violationList = new ConstraintViolationList();

        $validFrom = $promotion->getResultingValue('valid_from');
        $validUntil = $promotion->getResultingValue('valid_until');
        $useCodes = $promotion->getResultingValue('use_codes');
        $useCodesIndividual = $promotion->getResultingValue('use_individual_codes');
        $pattern = (string) $promotion->getResultingValue('individual_code_pattern');
        $code = (string) $promotion->getResultingValue('code');
        $promotionId = $promotion->primaryKey['id'];

        $trimmedCode = trim($code);

        // if we have both a date from and until, make sure that
        // the dateUntil is always in the future.
        if ($validFrom !== null && $validUntil !== null) {
            $dateFrom = new \DateTime($validFrom);
            $dateUntil = new \DateTime($validUntil);
            if ($dateUntil < $dateFrom) {
                $violationList->add($this->buildViolation(
                    'Expiration Date of Promotion must be after Start of Promotion',
                    $validUntil,
                    'validUntil',
                    'PROMOTION_VALID_UNTIL_VIOLATION'
                ));
            }
        }

        // check if we use global codes
        if ($useCodes && !$useCodesIndividual) {
            // make sure the code is not empty
            if ($trimmedCode === '') {
                $violationList->add($this->buildViolation(
                    'Please provide a valid code',
                    $code,
                    'code',
                    'PROMOTION_EMPTY_CODE_VIOLATION'
                ));
            }

            // if our code length is greater than the trimmed one,
            // this means we have leading or trailing whitespaces
            if (mb_strlen($code) > mb_strlen($trimmedCode)) {
                $violationList->add($this->buildViolation(
                    'Code may not have any leading or ending whitespaces',
                    $code,
                    'code',
                    'PROMOTION_CODE_WHITESPACE_VIOLATION'
                ));
            }
        }

        if ($pattern !== '' && $this->isCodePatternAlreadyUsed($pattern, $promotionId)) {
            $violationList->add($this->buildViolation(
                'Code Pattern already exists in other promotion. Please provide a different pattern.',
                $pattern,
                'individualCodePattern',
                'PROMOTION_DUPLICATE_PATTERN_VIOLATION'
            ));
        }

        // lookup global code if it does already exist in database
        if ($trimmedCode !== '' && $this->isCodeAlreadyUsed($trimmedCode, $promotionId)) {
            $violationList->add($this->buildViolation(
                'Code already exists in other promotion. Please provide a different code.',
                $trimmedCode,
                'code',
                'PROMOTION_DUPLICATED_CODE_VIOLATION'
            ));
        }

        return $violationList;
    }

    private function validateDiscount(OutcomeRow $discount): ConstraintViolationList
    {
        $violationList = new ConstraintViolationList();

        $type = $discount->getResultingValue('type');
        $value = $discount->getResultingValue('value');

        if ($value === null) {
            return $violationList;
        }

        if ($value < self::DISCOUNT_MIN_VALUE) {
            $violationList->add($this->buildViolation(
                'Value must not be less than ' . self::DISCOUNT_MIN_VALUE,
                $value,
                'value',
                'PROMOTION_DISCOUNT_MIN_VALUE_VIOLATION'
            ));
        }

        if (($type === PromotionDiscountEntity::TYPE_PERCENTAGE) && $value > self::DISCOUNT_PERCENTAGE_MAX_VALUE) {
            $violationList->add($this->buildViolation(
                'Absolute value must not greater than ' . self::DISCOUNT_PERCENTAGE_MAX_VALUE,
                $value,
                'value',
                'PROMOTION_DISCOUNT_MAX_VALUE_VIOLATION'
            ));
        }

        return $violationList;
    }

    /**
     * This helper function builds an easy violation
     * object for our validator.
     *
     * @param string $message the error message
     * @param mixed $invalidValue the actual invalid value
     * @param string $propertyPath the property path from the root value to the invalid value without initial slash
     * @param string $code the error code of the violation
     *
     * @return ConstraintViolationInterface the built constraint violation
     */
    private function buildViolation(string $message, mixed $invalidValue, string $propertyPath, string $code): ConstraintViolationInterface
    {
        return new ConstraintViolation(
            $message,
            '',
            [
                'value' => $invalidValue,
            ],
            $invalidValue,
            '/' . $propertyPath,
            $invalidValue,
            null,
            $code
        );
    }

    /**
     * True, if the provided pattern is already used in another promotion.
     */
    private function isCodePatternAlreadyUsed(string $pattern, string $promotionId): bool
    {
        $qb = $this->connection->createQueryBuilder();

        $query = $qb
            ->select('id')
            ->from('promotion')
            ->where($qb->expr()->eq('individual_code_pattern', ':pattern'))
            ->setParameter('pattern', $pattern);

        foreach ($query->executeQuery()->fetchFirstColumn() as $id) {
            if ($id !== $promotionId) {
                return true;
            }
        }

        return false;
    }

    /**
     * True, if the provided code is already used as global
     * or individual code in another promotion.
     */
    private function isCodeAlreadyUsed(string $code, string $promotionId): bool
    {
        $qb = $this->connection->createQueryBuilder();

        $query = $qb
            ->select('COUNT(*)')
            ->from('promotion_individual_code')
            ->where($qb->expr()->eq('code', ':code'))
            ->andWhere($qb->expr()->neq('promotion_id', ':promotion_id'))
            ->setParameter('code', $code)
            ->setParameter('promotion_id', $promotionId);

        if (((int) $query->executeQuery()->fetchOne()) > 0) {
            return true;
        }

        $qb = $this->connection->createQueryBuilder();

        $query = $qb
            ->select('COUNT(*)')
            ->from('promotion')
            ->where($qb->expr()->eq('code', ':code'))
            ->andWhere($qb->expr()->neq('id', ':id'))
            ->setParameter('code', $code)
            ->setParameter('id', $promotionId);

        return ((int) $query->executeQuery()->fetchOne()) > 0;
    }
}
