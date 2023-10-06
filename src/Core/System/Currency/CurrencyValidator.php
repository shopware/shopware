<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('core')]
class CurrencyValidator implements EventSubscriberInterface
{
    final public const VIOLATION_DELETE_DEFAULT_CURRENCY = 'delete_default_currency_violation';

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $commands = $event->getCommands();
        $violations = new ConstraintViolationList();

        foreach ($commands as $command) {
            if (!($command instanceof DeleteCommand) || $command->getDefinition()->getClass() !== CurrencyDefinition::class) {
                continue;
            }

            $pk = $command->getPrimaryKey();
            $id = mb_strtolower((string) Uuid::fromBytesToHex($pk['id']));
            if ($id !== Defaults::CURRENCY) {
                continue;
            }

            $msgTpl = 'The default currency {{ id }} cannot be deleted.';
            $parameters = ['{{ id }}' => $id];
            $msg = sprintf('The default currency %s cannot be deleted.', $id);
            $violation = new ConstraintViolation(
                $msg,
                $msgTpl,
                $parameters,
                null,
                '/' . $id,
                $id,
                null,
                self::VIOLATION_DELETE_DEFAULT_CURRENCY
            );

            $violations->add($violation);
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }
}
