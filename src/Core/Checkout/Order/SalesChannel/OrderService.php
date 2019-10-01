<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Validation\OrderValidationService;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderService
{
    /**
     * @var DataValidator
     */
    private $dataValidator;

    /**
     * @var OrderValidationService
     */
    private $orderValidationService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(
        DataValidator $dataValidator,
        OrderValidationService $orderValidationService,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService
    ) {
        $this->dataValidator = $dataValidator;
        $this->orderValidationService = $orderValidationService;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
    }

    /**
     * @throws ConstraintViolationException
     */
    public function createOrder(DataBag $data, SalesChannelContext $context): string
    {
        $this->validateOrderData($data, $context);

        $cart = $this->cartService->getCart($context->getToken(), $context);

        return $this->cartService->order($cart, $context);
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateOrderData(DataBag $data, SalesChannelContext $context): void
    {
        $definition = $this->getOrderCreateValidationDefinition($context);
        $violations = $this->dataValidator->getViolations($data->all(), $definition);

        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations, $data->all());
        }
    }

    private function getOrderCreateValidationDefinition(SalesChannelContext $context): DataValidationDefinition
    {
        $validation = $this->orderValidationService->buildCreateValidation($context);

        $validationEvent = new BuildValidationEvent($validation, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }
}
