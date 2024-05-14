<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\DataAbstractionLayer;

use Shopware\Core\Checkout\Payment\Event\PaymentMethodIndexerEvent;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class PaymentMethodIndexer extends EntityIndexer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly PaymentDistinguishableNameGenerator $distinguishableNameGenerator
    ) {
    }

    public function getName(): string
    {
        return 'payment_method.indexer';
    }

    /**
     * @param array{offset: int|null}|null $offset
     */
    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->paymentMethodRepository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new PaymentMethodIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(PaymentMethodDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        return new PaymentMethodIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        if (empty($ids)) {
            return;
        }

        // Create a new context with disabled-indexing state, because DAL is used inside to upsert payment methods.
        $newContext = Context::createFrom($message->getContext());
        // Apparently the new context loses the states of the old one during creation. So the old states get added back.
        $newContext->addState(EntityIndexerRegistry::DISABLE_INDEXING, ...$message->getContext()->getStates());
        $this->distinguishableNameGenerator->generateDistinguishablePaymentNames($newContext);

        $this->eventDispatcher->dispatch(new PaymentMethodIndexerEvent($ids, $message->getContext(), $message->getSkip()));
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->paymentMethodRepository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }
}
