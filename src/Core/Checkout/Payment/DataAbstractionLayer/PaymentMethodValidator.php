<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\DataAbstractionLayer;

use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodValidatorTest
 */
#[Package('checkout')]
final readonly class PaymentMethodValidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private WriteOutcome $writeOutcome)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        foreach ($this->writeOutcome->touchedRows($event->getCommandsForEntity(PaymentMethodDefinition::ENTITY_NAME), ['plugin_id']) as $paymentMethod) {
            if ($paymentMethod->isDeleted && $paymentMethod->getStoredValue('plugin_id') !== null) {
                throw PaymentException::pluginPaymentMethodDeleteRestriction();
            }
        }
    }
}
