<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('inventory')]
class UnitValidator implements EventSubscriberInterface
{
    final public const VIOLATION_INVALID_ISO_CODE = 'UNIT__INVALID_ISO_CODE';

    public function __construct(private readonly UnitIsoCodes $isoCodes)
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
        foreach ($event->getCommandsForEntity(UnitDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $isoCode = $command->getPayload()['iso_code'] ?? null;
            if (!\is_string($isoCode) || $this->isoCodes->isValid($isoCode)) {
                continue;
            }

            $violations = new ConstraintViolationList();
            $violations->add(new ConstraintViolation(
                \sprintf('"%s" is not a valid UN/ECE Recommendation 20 or 21 unit code.', $isoCode),
                '"{{ value }}" is not a valid UN/ECE Recommendation 20 or 21 unit code.',
                ['{{ value }}' => $isoCode],
                null,
                '/isoCode',
                $isoCode,
                null,
                self::VIOLATION_INVALID_ISO_CODE
            ));

            $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
        }
    }
}
