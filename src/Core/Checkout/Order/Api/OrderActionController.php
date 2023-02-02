<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Api;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopware\Core\Checkout\Payment\Exception\RefundProcessException;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class OrderActionController extends AbstractController
{
    private OrderService $orderService;

    private ApiVersionConverter $apiVersionConverter;

    private StateMachineDefinition $stateMachineDefinition;

    private Connection $connection;

    private PaymentRefundProcessor $paymentRefundProcessor;

    /**
     * @internal
     */
    public function __construct(
        OrderService $orderService,
        ApiVersionConverter $apiVersionConverter,
        StateMachineDefinition $stateMachineDefinition,
        Connection $connection,
        PaymentRefundProcessor $paymentRefundProcessor
    ) {
        $this->orderService = $orderService;
        $this->apiVersionConverter = $apiVersionConverter;
        $this->stateMachineDefinition = $stateMachineDefinition;
        $this->connection = $connection;
        $this->paymentRefundProcessor = $paymentRefundProcessor;
    }

    /**
     * @Since("6.1.0.0")
     * @Route("/api/_action/order/{orderId}/state/{transition}", name="api.action.order.state_machine.order.transition_state", methods={"POST"})
     */
    public function orderStateTransition(
        string $orderId,
        string $transition,
        Request $request,
        Context $context
    ): JsonResponse {
        $documentTypes = $request->request->all('documentTypes');
        if (\count($documentTypes) > 0) {
            $skipSentDocuments = (bool) $request->request->get('skipSentDocuments', false);
            $documentIds = $this->getDocumentIds('order', $orderId, $documentTypes, $skipSentDocuments);
        } else {
            $documentIds = $request->request->all('documentIds');
        }

        $mediaIds = $request->request->all('mediaIds');

        $context->addExtension(
            MailSendSubscriber::MAIL_CONFIG_EXTENSION,
            new MailSendSubscriberConfig(
                $request->request->get('sendMail', true) === false,
                $documentIds,
                $mediaIds
            )
        );

        $toPlace = $this->orderService->orderStateTransition(
            $orderId,
            $transition,
            $request->request,
            $context
        );

        $response = $this->apiVersionConverter->convertEntity(
            $this->stateMachineDefinition,
            $toPlace
        );

        return new JsonResponse($response);
    }

    /**
     * @Since("6.1.0.0")
     * @Route("/api/_action/order_transaction/{orderTransactionId}/state/{transition}", name="api.action.order.state_machine.order_transaction.transition_state", methods={"POST"})
     */
    public function orderTransactionStateTransition(
        string $orderTransactionId,
        string $transition,
        Request $request,
        Context $context
    ): JsonResponse {
        $documentTypes = $request->request->all('documentTypes');
        if (\count($documentTypes) > 0) {
            $skipSentDocuments = (bool) $request->request->get('skipSentDocuments', false);
            $documentIds = $this->getDocumentIds('order_transaction', $orderTransactionId, $documentTypes, $skipSentDocuments);
        } else {
            $documentIds = $request->request->all('documentIds');
        }

        $mediaIds = $request->request->all('mediaIds');

        $context->addExtension(
            MailSendSubscriber::MAIL_CONFIG_EXTENSION,
            new MailSendSubscriberConfig(
                $request->request->get('sendMail', true) === false,
                $documentIds,
                $mediaIds
            )
        );

        $toPlace = $this->orderService->orderTransactionStateTransition(
            $orderTransactionId,
            $transition,
            $request->request,
            $context
        );

        $response = $this->apiVersionConverter->convertEntity(
            $this->stateMachineDefinition,
            $toPlace
        );

        return new JsonResponse($response);
    }

    /**
     * @Since("6.1.0.0")
     * @Route("/api/_action/order_delivery/{orderDeliveryId}/state/{transition}", name="api.action.order.state_machine.order_delivery.transition_state", methods={"POST"})
     */
    public function orderDeliveryStateTransition(
        string $orderDeliveryId,
        string $transition,
        Request $request,
        Context $context
    ): JsonResponse {
        $documentTypes = $request->request->all('documentTypes');
        if (\count($documentTypes) > 0) {
            $skipSentDocuments = (bool) $request->request->get('skipSentDocuments', false);
            $documentIds = $this->getDocumentIds('order_delivery', $orderDeliveryId, $documentTypes, $skipSentDocuments);
        } else {
            $documentIds = $request->request->all('documentIds');
        }

        $mediaIds = $request->request->all('mediaIds');

        $context->addExtension(
            MailSendSubscriber::MAIL_CONFIG_EXTENSION,
            new MailSendSubscriberConfig(
                $request->request->get('sendMail', true) === false,
                $documentIds,
                $mediaIds
            )
        );

        $toPlace = $this->orderService->orderDeliveryStateTransition(
            $orderDeliveryId,
            $transition,
            $request->request,
            $context
        );

        $response = $this->apiVersionConverter->convertEntity(
            $this->stateMachineDefinition,
            $toPlace
        );

        return new JsonResponse($response);
    }

    /**
     * @Since("6.4.12.0")
     * @Route("/api/_action/order_transaction_capture_refund/{refundId}", name="api.action.order.order_transaction_capture_refund", methods={"POST"}, defaults={"_acl"={"order_refund.editor"}})
     *
     * @throws RefundProcessException
     */
    public function refundOrderTransactionCapture(string $refundId, Context $context): JsonResponse
    {
        $this->paymentRefundProcessor->processRefund($refundId, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string> $documentTypes
     *
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     *
     * @return array<string>
     */
    private function getDocumentIds(string $entity, string $referencedId, array $documentTypes, bool $skipSentDocuments): array
    {
        if (!\in_array($entity, ['order', 'order_transaction', 'order_delivery'], true)) {
            throw new NotFoundHttpException();
        }

        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(hex(document.document_type_id)) as doc_type',
            'LOWER(hex(document.id)) as doc_id',
            'document.created_at as newest_date',
            'document.sent as sent',
        ]);
        $query->from('document', 'document');
        $query->innerJoin('document', 'document_type', 'document_type', 'document.document_type_id = document_type.id');
        $query->where('document.order_id = :orderId');

        if ($entity === 'order') {
            $query->setParameter('orderId', Uuid::fromHexToBytes($referencedId));
        } else {
            $fetchOrder = $this->connection->createQueryBuilder();
            $fetchOrder->select('order_id')->from($entity)->where('id = :id');

            $fetchOrder->setParameter('id', Uuid::fromHexToBytes($referencedId));

            $orderId = $fetchOrder->execute()->fetchOne();

            $query->setParameter('orderId', $orderId);
        }

        $query->andWhere('document_type.technical_name IN (:documentTypes)');
        $query->orderBy('document.created_at', 'DESC');

        $query->setParameter('documentTypes', $documentTypes, Connection::PARAM_STR_ARRAY);

        $documents = $query->execute()->fetchAllAssociative();

        $documentsGroupByType = FetchModeHelper::group($documents);

        $documentIds = [];
        foreach ($documentsGroupByType as $documents) {
            // Latest document of type
            $document = $documents[0];

            if ($skipSentDocuments && $document['sent']) {
                continue;
            }

            $documentIds[] = $document['doc_id'];
        }

        return $documentIds;
    }
}
