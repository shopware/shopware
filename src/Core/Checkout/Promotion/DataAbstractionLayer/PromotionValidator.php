<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\DataAbstractionLayer;

use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Checkout\Promotion\DataAbstractionLayer\PromotionValidatorTest
 */
#[Package('checkout')]
class PromotionValidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly WriteOutcome $writeOutcome)
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
        foreach ($this->writeOutcome->touchedRows($event->getCommandsForEntity(PromotionDefinition::ENTITY_NAME), ['order_count']) as $promotion) {
            if ($promotion->isDeleted && $promotion->getStoredValue('order_count') > 0) {
                throw PromotionException::promotionUsedDeleteRestriction();
            }
        }
    }
}
