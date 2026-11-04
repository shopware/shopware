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
    final public const VIOLATION_INVALID_UNECE_CODE = 'UNIT__INVALID_UNECE_CODE';

    public function __construct(private readonly UnitUneceCodes $uneceCodes)
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

            $uneceCode = $command->getPayload()['unece_code'] ?? null;
            if (!\is_string($uneceCode) || $this->uneceCodes->isValid($uneceCode)) {
                continue;
            }

            $violations = new ConstraintViolationList();
            $violations->add(new ConstraintViolation(
                \sprintf('"%s" is not a valid UN/ECE Recommendation 20 or 21 unit code.', $uneceCode),
                '"{{ value }}" is not a valid UN/ECE Recommendation 20 or 21 unit code.',
                ['{{ value }}' => $uneceCode],
                null,
                '/uneceCode',
                $uneceCode,
                null,
                self::VIOLATION_INVALID_UNECE_CODE
            ));

            $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
        }
    }
}
