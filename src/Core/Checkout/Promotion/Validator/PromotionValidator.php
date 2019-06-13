<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Validator;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

class PromotionValidator implements EventSubscriberInterface
{
    /**
     * this is the min value for all types
     * (absolute, percentage, ...)
     */
    private const DISCOUNT_MIN_VALUE = 0.01;

    /**
     * this is used for the maximum allowed
     * percentage discount.
     */
    private const DISCOUNT_PERCENTAGE_MAX_VALUE = 100.0;

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    /**
     * This function validates our incoming delta-values for promotions
     * and its aggregation. It does only check for business relevant rules and logic.
     * All primitive "required" constraints are done inside the definition of the entity.
     *
     *
     * @throws WriteConstraintViolationException
     */
    public function preValidate(PreWriteValidationEvent $event): void
    {
        $violationList = new ConstraintViolationList();
        $writeCommands = $event->getCommands();

        /** @var WriteCommandInterface $command */
        foreach ($writeCommands as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            switch (get_class($command->getDefinition())) {
                case PromotionDefinition::class:
                    $this->validatePromotion($command->getPayload(), $violationList);
                    break;

                case PromotionDiscountDefinition::class:
                    $this->validateDiscount($command->getPayload(), $violationList);
                    break;
            }
        }

        if ($violationList->count() > 0) {
            throw new WriteConstraintViolationException($violationList);
        }
    }

    /**
     * Validates the provided Promotion data and adds
     * violations to the provided list of violations, if found.
     *
     * @param array                   $payload       the incoming delta-data
     * @param ConstraintViolationList $violationList the list of violations that needs to be filled
     *
     * @throws \Exception
     */
    private function validatePromotion(array $payload, ConstraintViolationList $violationList): void
    {
        /** @var bool|null $useCodes */
        $useCodes = $this->getValue($payload, 'use_codes', null);

        /** @var string|null $code */
        $code = $this->getValue($payload, 'code', null);

        $validFrom = $this->getValue($payload, 'valid_from', null);

        /** @var string|null $validUntil */
        $validUntil = $this->getValue($payload, 'valid_until', null);

        if ($useCodes !== null) {
            if ($code === null) {
                $code = '';
            }

            $trimmedCode = trim($code);

            if ($useCodes && trim($code) === '') {
                $violationList->add($this->buildViolation('Please provide a valid code', $code, 'code'));
            }

            if ($useCodes && strlen($code) > strlen($trimmedCode)) {
                $violationList->add($this->buildViolation('Code may not have any leading or ending whitespaces', $code, 'code'));
            }
        }

        // if we have both a date from and until, make sure that
        // the dateUntil is always in the future.
        if ($validFrom !== null && $validUntil !== null) {
            // now convert into real date times
            // and start comparing them
            $dateFrom = new \DateTime($validFrom);
            $dateUntil = new \DateTime($validUntil);
            if ($dateUntil < $dateFrom) {
                $violationList->add($this->buildViolation('Expiration Date of Promotion must be after Start of Promotion', $payload['valid_until'], 'validUntil'));
            }
        }
    }

    /**
     * Validates the provided PromotionDiscount data and adds
     * violations to the provided list of violations, if found.
     *
     * @param array                   $payload       the incoming delta-data
     * @param ConstraintViolationList $violationList the list of violations that needs to be filled
     */
    private function validateDiscount(array $payload, ConstraintViolationList $violationList): void
    {
        /** @var string $type */
        $type = $this->getValue($payload, 'type', '');

        /** @var float|null $value */
        $value = $this->getValue($payload, 'value', null);

        if ($value === null) {
            return;
        }

        if ($value < self::DISCOUNT_MIN_VALUE) {
            $violationList->add($this->buildViolation('Value must not be less than ' . self::DISCOUNT_MIN_VALUE, $value, 'value'));
        }

        switch ($type) {
            case PromotionDiscountEntity::TYPE_PERCENTAGE:
                if ($value > self::DISCOUNT_PERCENTAGE_MAX_VALUE) {
                    $violationList->add($this->buildViolation('Absolute value must not greater than ' . self::DISCOUNT_PERCENTAGE_MAX_VALUE, $value, 'value'));
                }
                break;
        }
    }

    /**
     * Gets a value from an array. It also does clean checks if
     * the key is set, and also provides the option for default values.
     *
     * @param array             $data    the data array
     * @param string            $key     the requested key in the array
     * @param string|float|null $default a default value (or null)
     *
     * @return mixed the object found in the key, or the default value
     */
    private function getValue(array $data, string $key, $default)
    {
        if (!isset($data[$key])) {
            return $default;
        }

        return $data[$key];
    }

    /**
     * This helper function builds an easy violation
     * object for our validator.
     *
     * @param string            $message      the error message
     * @param string|float|null $invalidValue the actual invalid value
     *
     * @return ConstraintViolationInterface the built constraint violation
     */
    private function buildViolation(string $message, $invalidValue, ?string $propertyPath): ConstraintViolationInterface
    {
        return new ConstraintViolation(
            $message,
            '',
            [
                'value' => $invalidValue,
            ],
            $invalidValue,
            $propertyPath,
            $invalidValue
        );
    }
}
