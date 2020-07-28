<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

class CustomerGroupValidator implements EventSubscriberInterface
{
    public const VIOLATION_DELETE_DEFAULT_CUSTOMER_GROUP = 'delete_default_customer_group_violation';

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $commands = $event->getCommands();

        foreach ($commands as $command) {
            if ($command instanceof CascadeDeleteCommand || $command->getDefinition()->getClass() !== CustomerGroupDefinition::class) {
                continue;
            }

            $pk = $command->getPrimaryKey();
            $id = \mb_strtolower(Uuid::fromBytesToHex($pk['id']));

            if (!$command instanceof DeleteCommand || $id !== Defaults::FALLBACK_CUSTOMER_GROUP) {
                continue;
            }

            $violations = new ConstraintViolationList();
            $violations->add($this->buildViolation(
                'The default customer group {{ id }} cannot be deleted.',
                ['{{ id }}' => $id],
                null,
                '/' . $id,
                $id,
                self::VIOLATION_DELETE_DEFAULT_CUSTOMER_GROUP
            ));
            $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
        }
    }

    private function buildViolation(
        string $messageTemplate,
        array $parameters,
        $root = null,
        ?string $propertyPath = null,
        ?string $invalidValue = null,
        ?string $code = null
    ): ConstraintViolationInterface {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            $root,
            $propertyPath,
            $invalidValue,
            null,
            $code
        );
    }
}
