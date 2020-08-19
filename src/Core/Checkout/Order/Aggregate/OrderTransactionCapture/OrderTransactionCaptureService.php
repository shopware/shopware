<?php

declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture;

use Shopware\Core\Checkout\Cart\Exception\OrderTransactionNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class OrderTransactionCaptureService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionCaptureRepository;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(
        EntityRepositoryInterface $orderTransactionRepository,
        EntityRepositoryInterface $orderTransactionCaptureRepository,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->orderTransactionCaptureRepository = $orderTransactionCaptureRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    public function createOrderTransactionCaptureForFullAmount(string $orderTransactionId, Context $context): string
    {
        $orderTransaction = $this->fetchOrderTransaction($orderTransactionId, $context);

        return $this->createOrderTransactionCapture(
            $orderTransactionId,
            $orderTransaction->getAmount()->getTotalPrice(),
            $context
        );
    }

    public function createOrderTransactionCaptureForCustomAmount(
        string $orderTransactionId,
        float $customCaptureAmount,
        Context $context
    ): string {
        $orderTransaction = $this->fetchOrderTransaction($orderTransactionId, $context);
        if (FloatComparator::greaterThan($customCaptureAmount, $orderTransaction->getAmount()->getTotalPrice())) {
            throw new \InvalidArgumentException(sprintf(
                'Capture amount %f exceeds the order transactions amount of %f',
                $customCaptureAmount,
                $orderTransaction->getAmount()->getTotalPrice()
            ));
        }

        return $this->createOrderTransactionCapture($orderTransactionId, $customCaptureAmount, $context);
    }

    public function deleteOrderTransactionCapture(string $orderTransactionCaptureId, Context $context): void
    {
        $this->orderTransactionRepository->delete([[
            'id' => $orderTransactionCaptureId,
        ]], $context);
    }

    private function fetchOrderTransaction(string $orderTransactionId, Context $context): OrderTransactionEntity
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('amount');

        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();
        if ($orderTransaction === null) {
            throw new OrderTransactionNotFoundException($orderTransactionId);
        }

        return $orderTransaction;
    }

    private function createOrderTransactionCapture(
        string $orderTransactionId,
        float $captureAmount,
        Context $context
    ): string {
        $orderTransactionCaptureId = Uuid::randomHex();
        $this->orderTransactionCaptureRepository->create(
            [
                [
                    'id' => $orderTransactionCaptureId,
                    'transactionId' => $orderTransactionId,
                    'amount' => $captureAmount,
                    'stateId' => $this->stateMachineRegistry->getInitialState(
                        OrderTransactionCaptureStates::STATE_MACHINE,
                        $context
                    )->getId(),
                ],
            ],
            $context
        );

        return $orderTransactionCaptureId;
    }
}
