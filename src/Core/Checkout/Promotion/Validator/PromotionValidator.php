<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Validator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\Api\Exception\ResourceNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
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
    private const DISCOUNT_MIN_VALUE = 0.00;

    /**
     * this is used for the maximum allowed
     * percentage discount.
     */
    private const DISCOUNT_PERCENTAGE_MAX_VALUE = 100.0;

    private Connection $connection;

    private array $databasePromotions;

    private array $databaseDiscounts;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

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
     * @throws WriteConstraintViolationException
     */
    public function preValidate(PreWriteValidationEvent $event): void
    {
        $this->collect($event->getCommands());

        $violationList = new ConstraintViolationList();
        $writeCommands = $event->getCommands();

        foreach ($writeCommands as $index => $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            switch (\get_class($command->getDefinition())) {
                case PromotionDefinition::class:

                    /** @var string $promotionId */
                    $promotionId = $command->getPrimaryKey()['id'];

                    try {
                        /** @var array $promotion */
                        $promotion = $this->getPromotionById($promotionId);
                    } catch (ResourceNotFoundException $ex) {
                        $promotion = [];
                    }

                    $this->validatePromotion(
                        $promotion,
                        $command->getPayload(),
                        $violationList,
                        $index
                    );

                    break;

                case PromotionDiscountDefinition::class:

                    /** @var string $discountId */
                    $discountId = $command->getPrimaryKey()['id'];

                    try {
                        /** @var array $discount */
                        $discount = $this->getDiscountById($discountId);
                    } catch (ResourceNotFoundException $ex) {
                        $discount = [];
                    }

                    $this->validateDiscount(
                        $discount,
                        $command->getPayload(),
                        $violationList,
                        $index
                    );

                    break;
            }
        }

        if ($violationList->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violationList));
        }
    }

    /**
     * This function collects all database data that might be
     * required for any of the received entities and values.
     *
     * @throws ResourceNotFoundException
     * @throws \Doctrine\DBAL\DBALException
     */
    private function collect(array $writeCommands): void
    {
        $promotionIds = [];
        $discountIds = [];

        /** @var WriteCommand $command */
        foreach ($writeCommands as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            switch (\get_class($command->getDefinition())) {
                case PromotionDefinition::class:
                    $promotionIds[] = $command->getPrimaryKey()['id'];

                    break;

                case PromotionDiscountDefinition::class:
                    $discountIds[] = $command->getPrimaryKey()['id'];

                    break;
            }
        }

        // why do we have inline sql queries in here?
        // because we want to avoid any other private functions that accidentally access
        // the database. all private getters should only access the local in-memory list
        // to avoid additional database queries.

        $promotionQuery = $this->connection->executeQuery(
            'SELECT * FROM `promotion` WHERE `id` IN (:ids)',
            ['ids' => $promotionIds],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->databasePromotions = $promotionQuery->fetchAll();

        $discountQuery = $this->connection->executeQuery(
            'SELECT * FROM `promotion_discount` WHERE `id` IN (:ids)',
            ['ids' => $discountIds],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->databaseDiscounts = $discountQuery->fetchAll();
    }

    /**
     * Validates the provided Promotion data and adds
     * violations to the provided list of violations, if found.
     *
     * @param array                   $promotion     the current promotion from the database as array type
     * @param array                   $payload       the incoming delta-data
     * @param ConstraintViolationList $violationList the list of violations that needs to be filled
     * @param int                     $index         the index of this promotion in the command queue
     *
     * @throws \Exception
     */
    private function validatePromotion(array $promotion, array $payload, ConstraintViolationList $violationList, int $index): void
    {
        /** @var string|null $validFrom */
        $validFrom = $this->getValue($payload, 'valid_from', $promotion);

        /** @var string|null $validUntil */
        $validUntil = $this->getValue($payload, 'valid_until', $promotion);

        /** @var bool $useCodes */
        $useCodes = $this->getValue($payload, 'use_codes', $promotion);

        /** @var bool $useCodesIndividual */
        $useCodesIndividual = $this->getValue($payload, 'use_individual_codes', $promotion);

        /** @var string|null $pattern */
        $pattern = $this->getValue($payload, 'individual_code_pattern', $promotion);

        /** @var string|null $promotionId */
        $promotionId = $this->getValue($payload, 'id', $promotion);

        /** @var string|null $code */
        $code = $this->getValue($payload, 'code', $promotion);

        if ($code === null) {
            $code = '';
        }

        if ($pattern === null) {
            $pattern = '';
        }

        $trimmedCode = trim($code);

        // if we have both a date from and until, make sure that
        // the dateUntil is always in the future.
        if ($validFrom !== null && $validUntil !== null) {
            // now convert into real date times
            // and start comparing them
            $dateFrom = new \DateTime($validFrom);
            $dateUntil = new \DateTime($validUntil);
            if ($dateUntil < $dateFrom) {
                $violationList->add($this->buildViolation(
                    'Expiration Date of Promotion must be after Start of Promotion',
                    $payload['valid_until'],
                    'validUntil',
                    'PROMOTION_VALID_UNTIL_VIOLATION',
                    $index
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
                    'PROMOTION_EMPTY_CODE_VIOLATION',
                    $index
                ));
            }

            // if our code length is greater than the trimmed one,
            // this means we have leading or trailing whitespaces
            if (mb_strlen($code) > mb_strlen($trimmedCode)) {
                $violationList->add($this->buildViolation(
                    'Code may not have any leading or ending whitespaces',
                    $code,
                    'code',
                    'PROMOTION_CODE_WHITESPACE_VIOLATION',
                    $index
                ));
            }
        }

        if ($pattern !== '' && $this->isCodePatternAlreadyUsed($pattern, $promotionId)) {
            $violationList->add($this->buildViolation(
                'Code Pattern already exists in other promotion. Please provide a different pattern.',
                $pattern,
                'individualCodePattern',
                'PROMOTION_DUPLICATE_PATTERN_VIOLATION',
                $index
            ));
        }

        // lookup global code if it does already exist in database
        if ($trimmedCode !== '' && $this->isCodeAlreadyUsed($trimmedCode, $promotionId)) {
            $violationList->add($this->buildViolation(
                'Code already exists in other promotion. Please provide a different code.',
                $trimmedCode,
                'code',
                'PROMOTION_DUPLICATED_CODE_VIOLATION',
                $index
            ));
        }
    }

    /**
     * Validates the provided PromotionDiscount data and adds
     * violations to the provided list of violations, if found.
     *
     * @param array                   $discount      the discount as array from the database
     * @param array                   $payload       the incoming delta-data
     * @param ConstraintViolationList $violationList the list of violations that needs to be filled
     */
    private function validateDiscount(array $discount, array $payload, ConstraintViolationList $violationList, int $index): void
    {
        /** @var string $type */
        $type = $this->getValue($payload, 'type', $discount);

        /** @var float|null $value */
        $value = $this->getValue($payload, 'value', $discount);

        if ($value === null) {
            return;
        }

        if ($value < self::DISCOUNT_MIN_VALUE) {
            $violationList->add($this->buildViolation(
                'Value must not be less than ' . self::DISCOUNT_MIN_VALUE,
                $value,
                'value',
                'PROMOTION_DISCOUNT_MIN_VALUE_VIOLATION',
                $index
            ));
        }

        switch ($type) {
            case PromotionDiscountEntity::TYPE_PERCENTAGE:
                if ($value > self::DISCOUNT_PERCENTAGE_MAX_VALUE) {
                    $violationList->add($this->buildViolation(
                        'Absolute value must not greater than ' . self::DISCOUNT_PERCENTAGE_MAX_VALUE,
                        $value,
                        'value',
                        'PROMOTION_DISCOUNT_MAX_VALUE_VIOLATION',
                        $index
                    ));
                }

                break;
        }
    }

    /**
     * Gets a value from an array. It also does clean checks if
     * the key is set, and also provides the option for default values.
     *
     * @param array  $data  the data array
     * @param string $key   the requested key in the array
     * @param array  $dbRow the db row of from the database
     *
     * @return mixed the object found in the key, or the default value
     */
    private function getValue(array $data, string $key, array $dbRow)
    {
        // try in our actual data set
        if (isset($data[$key])) {
            return $data[$key];
        }

        // try in our db row fallback
        if (isset($dbRow[$key])) {
            return $dbRow[$key];
        }

        // use default
        return null;
    }

    /**
     * @throws ResourceNotFoundException
     *
     * @return array|mixed
     */
    private function getPromotionById(string $id)
    {
        /** @var array $promotion */
        foreach ($this->databasePromotions as $promotion) {
            if ($promotion['id'] === $id) {
                return $promotion;
            }
        }

        throw new ResourceNotFoundException('promotion', [$id]);
    }

    /**
     * @throws ResourceNotFoundException
     *
     * @return array|mixed
     */
    private function getDiscountById(string $id)
    {
        /** @var array $discount */
        foreach ($this->databaseDiscounts as $discount) {
            if ($discount['id'] === $id) {
                return $discount;
            }
        }

        throw new ResourceNotFoundException('promotion_discount', [$id]);
    }

    /**
     * This helper function builds an easy violation
     * object for our validator.
     *
     * @param string $message      the error message
     * @param mixed  $invalidValue the actual invalid value
     * @param string $propertyPath the property path from the root value to the invalid value without initial slash
     * @param string $code         the error code of the violation
     * @param int    $index        the position of this entity in the command queue
     *
     * @return ConstraintViolationInterface the built constraint violation
     */
    private function buildViolation(string $message, $invalidValue, string $propertyPath, string $code, int $index): ConstraintViolationInterface
    {
        $formattedPath = "/{$index}/{$propertyPath}";

        return new ConstraintViolation(
            $message,
            '',
            [
                'value' => $invalidValue,
            ],
            $invalidValue,
            $formattedPath,
            $invalidValue,
            null,
            $code
        );
    }

    /**
     * True, if the provided pattern is already used in another promotion.
     */
    private function isCodePatternAlreadyUsed(string $pattern, ?string $promotionId): bool
    {
        $qb = $this->connection->createQueryBuilder();

        $query = $qb
            ->select('id')
            ->from('promotion')
            ->where($qb->expr()->eq('individual_code_pattern', ':pattern'))
            ->setParameter(':pattern', $pattern);

        $promotions = $query->execute()->fetchAll();

        /** @var array $p */
        foreach ($promotions as $p) {
            // if we have a promotion id to verify
            // and a promotion with another id exists, then return that is used
            if ($promotionId !== null && $p['id'] !== $promotionId) {
                return true;
            }
        }

        return false;
    }

    /**
     * True, if the provided code is already used as global
     * or individual code in another promotion.
     */
    private function isCodeAlreadyUsed(string $code, ?string $promotionId): bool
    {
        $qb = $this->connection->createQueryBuilder();

        // check if individual code.
        // if we dont have a promotion Id only
        // check if its existing somewhere,
        // if we have an Id, verify if it's existing in another promotion
        $query = $qb
            ->select('id')
            ->from('promotion_individual_code')
            ->where($qb->expr()->eq('code', ':code'))
            ->setParameter(':code', $code);

        if ($promotionId !== null) {
            $query->andWhere($qb->expr()->neq('promotion_id', ':promotion_id'))
                ->setParameter(':promotion_id', $promotionId);
        }

        $existingIndividual = \count($query->execute()->fetchAll()) > 0;

        if ($existingIndividual) {
            return true;
        }

        $qb = $this->connection->createQueryBuilder();

        // check if it is a global promotion code.
        // again with either an existing promotion Id
        // or without one.
        $query
            = $qb->select('id')
            ->from('promotion')
            ->where($qb->expr()->eq('code', ':code'))
            ->setParameter(':code', $code);

        if ($promotionId !== null) {
            $query->andWhere($qb->expr()->neq('id', ':id'))
                ->setParameter(':id', $promotionId);
        }

        return \count($query->execute()->fetchAll()) > 0;
    }
}
