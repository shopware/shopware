<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Validator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

class ShippingMethodValidator implements EventSubscriberInterface
{
    public const VIOLATION_TAX_TYPE_INVALID = 'tax_type_invalid';

    public const VIOLATION_TAX_ID_REQUIRED = 'c1051bb4-d103-4f74-8988-acbcafc7fdc3';

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents()
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $allowTypes = [
            ShippingMethodEntity::TAX_TYPE_FIXED,
            ShippingMethodEntity::TAX_TYPE_AUTO,
            ShippingMethodEntity::TAX_TYPE_HIGHEST,
        ];

        $writeCommands = $event->getCommands();

        foreach ($writeCommands as $command) {
            $violations = new ConstraintViolationList();

            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            if ($command->getDefinition()->getClass() !== ShippingMethodDefinition::class) {
                continue;
            }

            $shippingMethod = $this->findShippingMethod($command->getPrimaryKey()['id']);

            $payload = $command->getPayload();

            /** @var string|null $taxType */
            $taxType = $this->getValue($payload, 'tax_type', $shippingMethod);

            /** @var string|null $taxId */
            $taxId = $this->getValue($payload, 'tax_id', $shippingMethod);

            if ($taxType && !\in_array($taxType, $allowTypes, true)) {
                $violations->add(
                    $this->buildViolation(
                        'The selected tax type {{ type }} is invalid.',
                        ['{{ type }}' => $taxType],
                        '/taxType',
                        $taxType,
                        self::VIOLATION_TAX_TYPE_INVALID
                    )
                );
            }

            if ($taxType === ShippingMethodEntity::TAX_TYPE_FIXED && !$taxId) {
                $violations->add(
                    $this->buildViolation(
                        'The defined tax rate is required when fixed tax present',
                        ['{{ taxId }}' => null],
                        '/taxId',
                        $taxType,
                        self::VIOLATION_TAX_ID_REQUIRED
                    )
                );
            }

            if ($violations->count() > 0) {
                $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
            }
        }
    }

    private function findShippingMethod(string $shippingMethodId): array
    {
        $shippingMethod = $this->connection->executeQuery(
            'SELECT `tax_type`, `tax_id` FROM `shipping_method` WHERE `id` = :id',
            ['id' => $shippingMethodId]
        );

        return $shippingMethod->fetchAll();
    }

    private function buildViolation(
        string $messageTemplate,
        array $parameters,
        string $propertyPath,
        string $invalidValue,
        string $code
    ): ConstraintViolationInterface {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            null,
            $propertyPath,
            $invalidValue,
            null,
            $code
        );
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
}
