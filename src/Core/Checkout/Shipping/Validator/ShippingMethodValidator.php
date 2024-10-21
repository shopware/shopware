<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Validator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('checkout')]
class ShippingMethodValidator implements EventSubscriberInterface
{
    final public const VIOLATION_TAX_TYPE_INVALID = 'tax_type_invalid';

    final public const VIOLATION_TAX_ID_REQUIRED = NotBlank::IS_BLANK_ERROR;
    private const ALLOWED_TAX_TYPES = [
        ShippingMethodEntity::TAX_TYPE_FIXED,
        ShippingMethodEntity::TAX_TYPE_AUTO,
        ShippingMethodEntity::TAX_TYPE_HIGHEST,
    ];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            if ($command->getEntityName() !== ShippingMethodDefinition::ENTITY_NAME) {
                continue;
            }

            $shippingMethod = $this->findShippingMethod($command->getPrimaryKey()['id']);
            $payload = $command->getPayload();

            $taxType = $payload['tax_type'] ?? $shippingMethod['tax_type'] ?? null;
            \assert($taxType === null || \is_string($taxType));

            $taxId = $payload['tax_id'] ?? $shippingMethod['tax_id'] ?? null;
            \assert($taxId === null || \is_string($taxId));

            $violations = new ConstraintViolationList();
            if ($taxType && !\in_array($taxType, self::ALLOWED_TAX_TYPES, true)) {
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

            // Use `Uuid::fromBytesToHex` to validate the binary encoded `taxId`
            if ($taxType === ShippingMethodEntity::TAX_TYPE_FIXED && ($taxId === null || !Uuid::fromBytesToHex($taxId))) {
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

    /**
     * @return array<string, mixed>
     */
    private function findShippingMethod(string $shippingMethodId): array
    {
        return $this->connection->executeQuery(
            'SELECT `tax_type`, `tax_id` FROM `shipping_method` WHERE `id` = :id',
            ['id' => $shippingMethodId]
        )->fetchAssociative() ?: [];
    }

    /**
     * @param array<string, mixed> $parameters
     */
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
}
