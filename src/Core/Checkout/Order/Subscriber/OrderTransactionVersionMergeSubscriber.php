<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Subscriber;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeVersionMergeEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class OrderTransactionVersionMergeSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     */
    public function __construct(private readonly EntityRepository $orderTransactionRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeVersionMergeEvent::class => 'onBeforeVersionMerge',
        ];
    }

    public function onBeforeVersionMerge(BeforeVersionMergeEvent $event): void
    {
        $transactions = $event->writes['update'][OrderTransactionDefinition::ENTITY_NAME] ?? [];

        $ids = [];
        $versionId = null;
        foreach ($transactions as $transaction) {
            if (\array_key_exists('amount', $transaction)) {
                $ids[] = (string) $transaction['id'];
                $versionId = (string) $transaction['versionId'];
            }
        }

        if ($versionId === null) {
            return;
        }

        $criteria = new Criteria($ids);
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsAnyFilter('stateMachineState.technicalName', OrderService::ALLOWED_TRANSACTION_STATES),
        ]));

        $paymentNoLongerChangeable = $this->orderTransactionRepository
            ->searchIds($criteria, Context::createCLIContext()->createWithVersionId($versionId))
            ->getIds();

        $event->writes['update'][OrderTransactionDefinition::ENTITY_NAME] = array_map(
            static function (array $transaction) use ($paymentNoLongerChangeable): array {
                if (\in_array($transaction['id'], $paymentNoLongerChangeable, true)) {
                    unset($transaction['amount']);
                }

                return $transaction;
            },
            $transactions
        );
    }
}
