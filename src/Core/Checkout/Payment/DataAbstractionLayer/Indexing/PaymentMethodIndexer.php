<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Payment\Util\EventIdExtractor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PaymentMethodIndexer implements IndexerInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EventIdExtractor
     */
    private $eventIdExtractor;

    public function __construct(
        EntityRepositoryInterface $repository,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        EventIdExtractor $eventIdExtractor
    ) {
        $this->repository = $repository;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->eventIdExtractor = $eventIdExtractor;
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $ids = $this->eventIdExtractor->getPaymentMethodIds($event);
        $this->update($ids);
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();

        $iterator = $this->createIterator($context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing payment methods', $iterator->getTotal())
        );

        while ($ids = $iterator->fetchIds()) {
            $this->update($ids);
            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(\count($ids))
            );
        }

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished indexing payment methods')
        );
    }

    public function update(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $bytes = array_values(array_map(function ($id) { return Uuid::fromHexToBytes($id); }, $ids));

        $paymentRules = $this->connection->fetchAll(
            'SELECT id, rule_id 
             FROM payment_method 
             LEFT OUTER JOIN payment_method_rule ON payment_method.id = payment_method_rule.payment_method_id 
             WHERE id IN (:ids) ORDER BY id',
            ['ids' => $bytes],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $paymentRules = FetchModeHelper::group($paymentRules);
        foreach ($paymentRules as $paymentMethodId => $ruleIds) {
            $ruleIds = $this->mapIds($ruleIds);
            $serialized = json_encode($ruleIds);

            $this->connection->createQueryBuilder()
                ->update('payment_method')
                ->set('availability_rule_ids', ':serialize')
                ->where('id = :id')
                ->setParameter('id', $paymentMethodId)
                ->setParameter('serialize', $serialized)
                ->execute();
        }
    }

    protected function mapIds(array $ids): array
    {
        if (count($ids) === 1 && $ids[0]['rule_id'] === null) {
            return [];
        }

        return array_map(function ($id) { return Uuid::fromBytesToHex($id['rule_id']); }, $ids);
    }

    private function createIterator(Context $context): RepositoryIterator
    {
        return new RepositoryIterator($this->repository, $context);
    }
}
