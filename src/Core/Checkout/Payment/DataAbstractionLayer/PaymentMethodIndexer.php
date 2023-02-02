<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\DataAbstractionLayer;

use Shopware\Core\Checkout\Payment\Event\PaymentMethodIndexerEvent;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentMethodIndexer extends EntityIndexer
{
    private IteratorFactory $iteratorFactory;

    private EventDispatcherInterface $eventDispatcher;

    private EntityRepositoryInterface $paymentMethodRepository;

    private PaymentDistinguishableNameGenerator $distinguishableNameGenerator;

    /**
     * @internal
     */
    public function __construct(
        IteratorFactory $iteratorFactory,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $paymentMethodRepository,
        PaymentDistinguishableNameGenerator $distinguishableNameGenerator
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->distinguishableNameGenerator = $distinguishableNameGenerator;
    }

    public function getName(): string
    {
        return 'payment_method.indexer';
    }

    /**
     * @param array|null $offset
     *
     * @deprecated tag:v6.5.0 The parameter $offset will be native typed
     */
    public function iterate(/*?array*/ $offset): ?EntityIndexingMessage
    {
        if ($offset !== null && !\is_array($offset)) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Parameter `$offset` of method "iterate()" in class "PaymentMethodIndexer" will be natively typed to `?array` in v6.5.0.0.'
            );
        }

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

        $this->distinguishableNameGenerator->generateDistinguishablePaymentNames($message->getContext());

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
