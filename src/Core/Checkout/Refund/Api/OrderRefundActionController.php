<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Refund\Api;

use Shopware\Core\Checkout\Refund\PaymentRefundProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class OrderRefundActionController extends AbstractController
{
    /**
     * @var PaymentRefundProcessor
     */
    private $paymentRefundProcessor;

    public function __construct(PaymentRefundProcessor $paymentRefundProcessor)
    {
        $this->paymentRefundProcessor = $paymentRefundProcessor;
    }

    /**
     * @Since("6.3.4.0")
     * @Route("/api/v{version}/_action/order-refund/{orderRefundId}/process", name="api.action.order-refund.process", methods={"POST"})
     */
    public function processOrderRefund(string $orderRefundId, Context $context): Response
    {
        $this->paymentRefundProcessor->processRefund($orderRefundId, $context);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
