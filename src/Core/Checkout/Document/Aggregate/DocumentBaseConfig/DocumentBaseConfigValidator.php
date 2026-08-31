<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentBaseConfigValidator implements EventSubscriberInterface
{
    final public const INVALID_PAYMENT_DUE_DATE = 'DOCUMENT_BASE_CONFIG_INVALID_PAYMENT_DUE_DATE';

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        $violations = new ConstraintViolationList();

        foreach ($event->getCommandsForEntity(DocumentBaseConfigDefinition::ENTITY_NAME) as $command) {
            if (!$command->hasField('config')) {
                continue;
            }

            $encodedConfig = $command->getPayload()['config'];
            if (!\is_string($encodedConfig)) {
                continue;
            }

            $config = json_decode($encodedConfig, true, 512, \JSON_THROW_ON_ERROR);
            $paymentDueDate = $config['paymentDueDate'] ?? null;

            // Empty value is still allowed.
            if ($paymentDueDate === null || $paymentDueDate === '') {
                continue;
            }

            if (\is_string($paymentDueDate) && $this->isValid($paymentDueDate)) {
                continue;
            }

            $messageTemplate = 'The payment due date must be a relative date such as "{{ example }}".';
            $parameters = ['{{ example }}' => '+14 days'];

            $violations->add(new ConstraintViolation(
                message: str_replace(\array_keys($parameters), \array_values($parameters), $messageTemplate),
                messageTemplate: $messageTemplate,
                parameters: $parameters,
                root: null,
                propertyPath: $command->getPath() . '/config/paymentDueDate',
                invalidValue: $paymentDueDate,
                code: self::INVALID_PAYMENT_DUE_DATE,
            ));
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(
                new WriteConstraintViolationException($violations),
            );
        }
    }

    private function isValid(string $value): bool
    {
        try {
            $this->clock->now()->modify($value);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
