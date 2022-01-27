<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Api;

use Doctrine\DBAL\Connection;
use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class OrderActionController extends AbstractController
{
    private OrderService $orderService;

    private ApiVersionConverter $apiVersionConverter;

    private StateMachineDefinition $stateMachineDefinition;

    private Connection $connection;

    public function __construct(
        OrderService $orderService,
        ApiVersionConverter $apiVersionConverter,
        StateMachineDefinition $stateMachineDefinition,
        Connection $connection
    ) {
        $this->orderService = $orderService;
        $this->apiVersionConverter = $apiVersionConverter;
        $this->stateMachineDefinition = $stateMachineDefinition;
        $this->connection = $connection;
    }

    /**
     * @Since("6.1.0.0")
     * @OA\Post(
     *     path="/_action/order/{orderId}/state/{transition}",
     *     summary="Transition an order to a new state",
     *     description="Changes the order state and informs the customer via email if configured.",
     *     operationId="orderStateTransition",
     *     tags={"Admin API", "Order Management"},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="sendMail",
     *                 description="Controls if a mail should be sent to the customer.",
     *                 @OA\Schema(type="boolean", default=true)
     *             ),
     *             @OA\Property(
     *                 property="documentIds",
     *                 description="A list of document identifiers that should be attached",
     *                 type="array",
     *                 @OA\Items(type="string", pattern="^[0-9a-f]{32}$")
     *             ),
     *             @OA\Property(
     *                 property="mediaIds",
     *                 description="A list of media identifiers that should be attached",
     *                 type="array",
     *                 @OA\Items(type="string", pattern="^[0-9a-f]{32}$")
     *             ),
     *             @OA\Property(
     *                 property="stateFieldName",
     *                 description="This is the state column within the order database table. There should be no need to change it from the default.",
     *                 type="string",
     *                 default="stateId"
     *             )
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="orderId",
     *         description="Identifier of the order.",
     *         @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="transition",
     *         description="The `action_name` of the `state_machine_transition`. For example `process` if the order state should change from open to in progress.

Note: If you choose a transition that is not available, you will get an error that lists possible transitions for the current state.",
     *         @OA\Schema(type="string"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Todo: Use ref of `state_machine_transition` here"
     *     )
     * )
     * @Route("/api/_action/order/{orderId}/state/{transition}", name="api.action.order.state_machine.order.transition_state", methods={"POST"})
     *
     * @throws OrderNotFoundException
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
     * @OA\Post(
     *     path="/_action/order_transaction/{orderTransactionId}/state/{transition}",
     *     summary="Transition an order transaction to a new state",
     *     description="Changes the order transaction state and informs the customer via email if configured.",
     *     operationId="orderTransactionStateTransition",
     *     tags={"Admin API", "Order Management"},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="sendMail",
     *                 description="Controls if a mail should be sent to the customer.",
     *                 @OA\Schema(type="boolean", default=true)
     *             ),
     *             @OA\Property(
     *                 property="documentIds",
     *                 description="A list of document identifiers that should be attached",
     *                 type="array",
     *                 @OA\Items(type="string", pattern="^[0-9a-f]{32}$")
     *             ),
     *             @OA\Property(
     *                 property="mediaIds",
     *                 description="A list of media identifiers that should be attached",
     *                 type="array",
     *                 @OA\Items(type="string", pattern="^[0-9a-f]{32}$")
     *             ),
     *             @OA\Property(
     *                 property="stateFieldName",
     *                 description="This is the state column within the order transaction database table. There should be no need to change it from the default.",
     *                 type="string",
     *                 default="stateId"
     *             )
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="orderTransactionId",
     *         description="Identifier of the order transaction.",
     *         @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="transition",
     *         description="The `action_name` of the `state_machine_transition`. For example `process` if the order state should change from open to in progress.

Note: If you choose a transition that is not available, you will get an error that lists possible transitions for the current state.",
     *         @OA\Schema(type="string"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns information about the transition that was made. `#/components/schemas/StateMachineTransition`"
     *     )
     * )
     * @Route("/api/_action/order_transaction/{orderTransactionId}/state/{transition}", name="api.action.order.state_machine.order_transaction.transition_state", methods={"POST"})
     *
     * @throws OrderNotFoundException
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
     * @OA\Post(
     *     path="/_action/order_delivery/{orderDeliveryId}/state/{transition}",
     *     summary="Transition an order delivery to a new state",
     *     description="Changes the order delivery state and informs the customer via email if configured.",
     *     operationId="orderDeliveryStateTransition",
     *     tags={"Admin API", "Order Management"},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="sendMail",
     *                 description="Controls if a mail should be send to the customer.",
     *                 @OA\Schema(type="boolean", default=true)
     *             ),
     *             @OA\Property(
     *                 property="documentIds",
     *                 description="A list of document identifiers that should be attached",
     *                 type="array",
     *                 @OA\Items(type="string", pattern="^[0-9a-f]{32}$")
     *             ),
     *             @OA\Property(
     *                 property="mediaIds",
     *                 description="A list of media identifiers that should be attached",
     *                 type="array",
     *                 @OA\Items(type="string", pattern="^[0-9a-f]{32}$")
     *             ),
     *             @OA\Property(
     *                 property="stateFieldName",
     *                 description="This is the state column within the order delivery database table. There should be no need to change it from the default.",
     *                 type="string",
     *                 default="stateId"
     *             )
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="orderDeliveryId",
     *         description="Identifier of the order delivery.",
     *         @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="transition",
     *         description="The `action_name` of the `state_machine_transition`. For example `process` if the order state should change from open to in progress.

Note: If you choose a transition which is not possible, you will get an error that lists possible transition for the actual state.",
     *         @OA\Schema(type="string"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Todo: Use ref of `state_machine_transition` here"
     *     )
     * )
     * @Route("/api/_action/order_delivery/{orderDeliveryId}/state/{transition}", name="api.action.order.state_machine.order_delivery.transition_state", methods={"POST"})
     *
     * @throws OrderNotFoundException
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

        if ($skipSentDocuments) {
            $query->andWhere('document.sent = 0');
        }

        $query->andWhere('document_type.technical_name IN (:documentTypes)');
        $query->orderBy('document.created_at', 'DESC');

        $query->setParameter('documentTypes', $documentTypes, Connection::PARAM_STR_ARRAY);

        $documents = $query->execute()->fetchAllAssociative();

        $documentsGroupByType = FetchModeHelper::group($documents);

        $documentIds = [];
        foreach ($documentsGroupByType as $document) {
            $documentIds[] = array_shift($document)['doc_id'];
        }

        return $documentIds;
    }
}
