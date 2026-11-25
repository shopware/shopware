<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Services are owned by the internal service lifecycle, which always runs in the system scope.
 *
 * This subscriber prohibits creating, modifying or deleting a service through the auto-generated
 * entity API (Admin and Sync), which writes in the crud scope and would otherwise bypass the
 * lifecycle and its requirement gate.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Service\Subscriber\ServiceWriteProtectionSubscriberTest
 */
#[Package('framework')]
readonly class ServiceWriteProtectionSubscriber implements EventSubscriberInterface
{
    final public const VIOLATION_NO_PERMISSION = 'service_write_not_allowed_violation';

    public function __construct(private WriteOutcome $writeOutcome)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'checkWrite',
        ];
    }

    public function checkWrite(PreWriteValidationEvent $event): void
    {
        // Every legitimate service mutation runs in the system scope (ServiceLifecycle elevates to it),
        // so a non-system write can never be the internal lifecycle. This also skips most writes cheaply.
        if ($event->getContext()->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        $violations = new ConstraintViolationList();
        foreach ($this->writeOutcome->touchedRows($event->getCommandsForEntity(AppDefinition::ENTITY_NAME), ['self_managed']) as $app) {
            if ($app->getPayloadValue('self_managed') || $app->getStoredValue('self_managed')) {
                $violations->add($this->buildViolation());
            }
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    private function buildViolation(): ConstraintViolation
    {
        return new ConstraintViolation(
            'A service cannot be created, modified or deleted through the API.',
            'A service cannot be created, modified or deleted through the API.',
            [],
            null,
            '/' . AppDefinition::ENTITY_NAME,
            null,
            null,
            self::VIOLATION_NO_PERMISSION
        );
    }
}
