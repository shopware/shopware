<?php declare(strict_types=1);

namespace Shopware\Payment\Controller;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Psr\Container\NotFoundExceptionInterface;
use Shopware\Api\Payment\Repository\PaymentMethodRepository;
use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Payment\Exception\InvalidTokenException;
use Shopware\Payment\Exception\TokenExpiredException;
use Shopware\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Payment\PaymentHandler\PaymentHandlerInterface;
use Shopware\Payment\Token\TokenFactory;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends Controller
{
    /**
     * @var PaymentMethodRepository
     */
    private $paymentMethodRepository;
    /**
     * @var TokenFactory
     */
    private $tokenFactory;

    public function __construct(TokenFactory $tokenFactory, PaymentMethodRepository $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->tokenFactory = $tokenFactory;
    }

    /**
     * @Route("/payment/finalize", name="payment_finalize", options={"seo"="false"})
     *
     * @throws InvalidTokenException
     * @throws TokenExpiredException
     * @throws UnknownPaymentMethodException
     * @throws InvalidArgumentException
     */
    public function finalizeAction(Request $request, StorefrontContext $context)
    {
        $shopContext = $context->getShopContext();
        $paymentToken = $this->tokenFactory->validateToken($request->get('token'));
        $this->tokenFactory->invalidateToken($paymentToken->getToken());

        $paymentHandler = $this->getPaymentHandlerById($paymentToken->getPaymentMethodId(), $shopContext);
        $paymentHandler->finalizePaymentAction($paymentToken->getTransactionId(), $request, $shopContext);

        return $this->redirectToRoute('checkout_pay', ['transaction' => $paymentToken->getTransactionId()]);
    }

    /**
     * @throws UnknownPaymentMethodException
     */
    private function getPaymentHandlerById(string $paymentMethodId, ShopContext $context): PaymentHandlerInterface
    {
        $paymentMethodCollection = $this->paymentMethodRepository->readBasic([$paymentMethodId], $context);

        if ($paymentMethodCollection->count() !== 1) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }

        return $this->getPaymentHandlerByClass($paymentMethodCollection->first()->getClass());
    }

    /**
     * @throws UnknownPaymentMethodException
     */
    private function getPaymentHandlerByClass(string $class): PaymentHandlerInterface
    {
        try {
            return $this->container->get($class);
        } catch (NotFoundExceptionInterface $e) {
            throw new UnknownPaymentMethodException($class);
        }
    }
}
