<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderService
{
    public const CUSTOMER_COMMENT_KEY = 'customerComment';
    public const AFFILIATE_CODE_KEY = 'affiliateCode';
    public const CAMPAIGN_CODE_KEY = 'campaignCode';

    /**
     * @var DataValidator
     */
    private $dataValidator;

    /**
     * @var ValidationServiceInterface|DataValidationFactoryInterface
     */
    private $orderValidationFactory;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @param ValidationServiceInterface|DataValidationFactoryInterface $orderValidationFactory
     */
    public function __construct(
        DataValidator $dataValidator,
        $orderValidationFactory,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService
    ) {
        $this->dataValidator = $dataValidator;
        $this->orderValidationFactory = $orderValidationFactory;
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
        $this->addCustomerComment($cart, $data);
        $this->addAffiliateTracking($cart, $data);

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
        if ($this->orderValidationFactory instanceof DataValidationFactoryInterface) {
            $validation = $this->orderValidationFactory->create($context);
        } else {
            $validation = $this->orderValidationFactory->buildCreateValidation($context->getContext());
        }

        $validationEvent = new BuildValidationEvent($validation, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }

    private function addCustomerComment(Cart $cart, DataBag $data): void
    {
        $customerComment = ltrim(rtrim((string) $data->get(self::CUSTOMER_COMMENT_KEY, '')));

        if ($customerComment === '') {
            return;
        }

        $cart->setCustomerComment($customerComment);
    }

    private function addAffiliateTracking(Cart $cart, DataBag $data): void
    {
        $affiliateCode = $data->get(self::AFFILIATE_CODE_KEY);
        $campaignCode = $data->get(self::CAMPAIGN_CODE_KEY);
        if ($affiliateCode !== null && $campaignCode !== null) {
            $cart->setAffiliateCode($affiliateCode);
            $cart->setCampaignCode($campaignCode);
        }
    }
}
