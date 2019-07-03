<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Validator;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\Api\Exception\ResourceNotFoundException;
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

    /**
     * @var Connection
     */
    private $connection;

    /** @var array */
    private $databasePromotions;

    /** @var array */
    private $databaseDiscounts;

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
     *
     * @throws WriteConstraintViolationException
     */
    public function preValidate(PreWriteValidationEvent $event): void
    {
        $this->collect($event->getCommands());

        $violationList = new ConstraintViolationList();
        $writeCommands = $event->getCommands();

        /** @var WriteCommandInterface $command */
        foreach ($writeCommands as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            switch (get_class($command->getDefinition())) {
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
                        $violationList
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
                        $violationList
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
    private function collect(array $writeCommands)
    {
        $promotionIds = [];
        $discountIds = [];

        /** @var WriteCommandInterface $command */
        foreach ($writeCommands as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            switch (get_class($command->getDefinition())) {
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

        /** @var ResultStatement $promotionQuery */
        $promotionQuery = $this->connection->executeQuery(
            'SELECT * FROM `promotion` WHERE `id` IN (:ids)',
            ['ids' => $promotionIds],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->databasePromotions = $promotionQuery->fetchAll() ?? [];

        $discountQuery = $this->connection->executeQuery(
            'SELECT * FROM `promotion_discount` WHERE `id` IN (:ids)',
            ['ids' => $discountIds],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->databaseDiscounts = $discountQuery->fetchAll() ?? [];
    }

    /**
     * Validates the provided Promotion data and adds
     * violations to the provided list of violations, if found.
     *
     * @param array                   $promotion     the current promotion from the database as array type
     * @param array                   $payload       the incoming delta-data
     * @param ConstraintViolationList $violationList the list of violations that needs to be filled
     *
     * @throws \Exception
     */
    private function validatePromotion(array $promotion, array $payload, ConstraintViolationList $violationList)
    {
        /** @var string|null $validFrom */
        $validFrom = $this->getValue($payload, 'valid_from', $promotion);

        /** @var string|null $validUntil */
        $validUntil = $this->getValue($payload, 'valid_until', $promotion);

        /** @var bool $useCodes */
        $useCodes = $this->getValue($payload, 'use_codes', $promotion);

        /** @var string|null $primaryKey */
        $primaryKey = $this->getValue($payload, 'id', $promotion);

        /** @var string|null $code */
        $code = $this->getValue($payload, 'code', $promotion);

        if ($code === null) {
            $code = '';
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
                $violationList->add($this->buildViolation('Expiration Date of Promotion must be after Start of Promotion', $payload['valid_until'], 'validUntil'));
            }
        }

        // check if we use global codes
        if ($useCodes) {
            // make sure the code is not empty
            if ($trimmedCode === '') {
                $violationList->add($this->buildViolation('Please provide a valid code', $code, 'code'));
            }

            // if our code length is greater than the trimmed one,
            // this means we have leading or trailing whitespaces
            if (strlen($code) > strlen($trimmedCode)) {
                $violationList->add($this->buildViolation('Code may not have any leading or ending whitespaces', $code, 'code'));
            }
        }

        // lookup global code if it does already exist in database
        if ($trimmedCode !== '' && !$this->isUnique($trimmedCode, $primaryKey)) {
            $violationList->add($this->buildViolation('Code already exists in other promotion. Please provide a different code.', $trimmedCode, 'code'));
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
    private function validateDiscount(array $discount, array $payload, ConstraintViolationList $violationList)
    {
        /** @var string $type */
        $type = $this->getValue($payload, 'type', $discount);

        /** @var float|null $value */
        $value = $this->getValue($payload, 'value', $discount);

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

    /**
     * Check if a global code does already exist in database
     *
     * @param string $id
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function isUnique(string $code, ?string $id): bool
    {
        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();

        $query = $qb->select('id')
            ->from('promotion')
            ->where($qb->expr()->eq('code', ':code'))
            ->setParameter(':code', $code);
        if ($id !== null) {
            $query->andWhere($qb->expr()->neq('id', ':id'))
                ->setParameter(':id', $id);
        }

        return !(count($query->execute()->fetchAll()) > 0);
    }
}
