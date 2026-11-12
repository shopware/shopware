<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class UserPreWriteValidator implements EventSubscriberInterface
{
    public const VIOLATION_USERNAME_WHITESPACE = 'USER__USERNAME_WHITESPACE';

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommandsForEntity(UserDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $username = $command->getPayload()['username'] ?? null;
            if (!\is_string($username) || $username === mb_trim($username)) {
                continue;
            }

            $message = 'The username must not contain leading or trailing whitespace.';
            $event->getExceptions()->add(new WriteConstraintViolationException(
                new ConstraintViolationList([
                    new ConstraintViolation($message, $message, [], null, '/username', $username, code: self::VIOLATION_USERNAME_WHITESPACE),
                ]),
                $command->getPath(),
            ));
        }
    }
}
