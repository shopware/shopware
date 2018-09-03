<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterface;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class PaymentService
{
    /**
     * @var PaymentTransactionChainProcessor
     */
    private $paymentProcessor;

    /**
     * @var TokenFactoryInterface
     */
    private $tokenFactory;

    /**
     * @var RepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var PaymentHandlerRegistry
     */
    private $paymentHandlerRegistry;

    public function __construct(
        PaymentTransactionChainProcessor $paymentProcessor,
        TokenFactoryInterface $tokenFactory,
        RepositoryInterface $paymentMethodRepository,
        PaymentHandlerRegistry $paymentHandlerRegistry
    ) {
        $this->paymentProcessor = $paymentProcessor;
        $this->tokenFactory = $tokenFactory;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentHandlerRegistry = $paymentHandlerRegistry;
    }

    /**
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     */
    public function handlePaymentByOrder(string $orderId, CheckoutContext $context, ?string $finishUrl = null): ?RedirectResponse
    {
        if (!Uuid::isValid($orderId)) {
            throw new InvalidOrderException($orderId);
        }

        $redirect = $this->paymentProcessor->process($orderId, $context->getContext(), $finishUrl);

        if ($redirect) {
            return $redirect;
        }

        return null;
    }

    /**
     * @throws UnknownPaymentMethodException
     * @throws TokenExpiredException
     */
    public function finalizeTransaction(string $paymentToken, Request $request, Context $context): string
    {
        $paymentToken = $this->parseToken($paymentToken, $context);

        $paymentHandler = $this->getPaymentHandlerById($paymentToken->getPaymentMethodId(), $context);
        $paymentHandler->finalize($paymentToken->getTransactionId(), $request, $context);

        return $paymentToken->getTransactionId();
    }

    /**
     * @throws TokenExpiredException
     */
    private function parseToken(string $token, Context $context): TokenStruct
    {
        $tokenStruct = $this->tokenFactory->parseToken(
            $token,
            $context
        );

        if ($tokenStruct->isExpired()) {
            throw new TokenExpiredException($tokenStruct->getToken());
        }

        $this->tokenFactory->invalidateToken(
            $tokenStruct->getToken(),
            $context
        );

        return $tokenStruct;
    }

    /**
     * @throws UnknownPaymentMethodException
     */
    private function getPaymentHandlerById(string $paymentMethodId, Context $context): PaymentHandlerInterface
    {
        $paymentMethods = $this->paymentMethodRepository->read(new ReadCriteria([$paymentMethodId]), $context);

        /** @var PaymentMethodStruct $paymentMethod */
        $paymentMethod = $paymentMethods->get($paymentMethodId);
        if (!$paymentMethod) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }

        return $this->paymentHandlerRegistry->get($paymentMethod->getClass());
    }
}
