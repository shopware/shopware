<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Payment\Exception\PluginPaymentMethodsDeleteRestrictionException;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final class PaymentMethodValidator implements EventSubscriberInterface
{
    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents()
    {
        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        $ids = $event->getDeletedPrimaryKeys(PaymentMethodDefinition::ENTITY_NAME);

        $ids = \array_column($ids, 'id');

        if (empty($ids)) {
            return;
        }

        $pluginIds = $this->connection->fetchOne(
            'SELECT id FROM payment_method WHERE id IN (:ids) AND plugin_id IS NOT NULL',
            ['ids' => $ids],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        if (!empty($pluginIds)) {
            throw new PluginPaymentMethodsDeleteRestrictionException();
        }
    }
}
