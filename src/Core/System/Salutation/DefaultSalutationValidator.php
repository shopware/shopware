<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @deprecated tag:v6.5.0 This subscriber will be superfluous once salutations
 * are fully optional and should be removed together with the flag FEATURE_NEXT_7739.
 */
class DefaultSalutationValidator implements EventSubscriberInterface
{
    public const VIOLATION_CODE = 'SYSTEM__DEFAULT_SALUTATION_LOCKED';

    private const MESSAGE = 'The default salutation entity may not be deleted.';

    public static function getSubscribedEvents()
    {
        if (Feature::isActive('FEATURE_NEXT_7739')) {
            return [];
        }

        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    /**
     * @internal
     */
    public function validate(PreWriteValidationEvent $event): void
    {
        $violations = new ConstraintViolationList();

        foreach ($event->getCommands() as $command) {
            if (!($command instanceof DeleteCommand)) {
                continue;
            }

            if ($command->getDefinition()->getClass() !== SalutationDefinition::class) {
                continue;
            }

            if (Uuid::fromBytesToHex($command->getPrimaryKey()['id']) !== Defaults::SALUTATION) {
                continue;
            }

            $violations->add(new ConstraintViolation(
                self::MESSAGE,
                null,
                [],
                null,
                '/',
                null,
                null,
                self::VIOLATION_CODE
            ));
        }

        if ($violations->count() < 1) {
            return;
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }
}
