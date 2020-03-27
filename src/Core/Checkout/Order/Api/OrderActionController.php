<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Api;

use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Service\MailServiceInterface;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class OrderActionController extends AbstractController
{
    private $stateMachineRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $mailTemplateRepository;

    /**
     * @var MailServiceInterface
     */
    private $mailService;

    /**
     * @var DocumentService
     */
    private $documentService;

    /**
     * @var EntityRepositoryInterface
     */
    private $documentRepository;

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    /**
     * @var StateMachineDefinition
     */
    private $stateMachineDefinition;

    /**
     * @var OrderService
     */
    private $orderService;

    public function __construct(
        StateMachineRegistry $stateMachineRegistry,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $mailTemplateRepository,
        EntityRepositoryInterface $documentRepository,
        MailServiceInterface $mailService,
        DocumentService $documentService,
        ApiVersionConverter $apiVersionConverter,
        StateMachineDefinition $stateMachineDefinition,
        OrderService $orderService
    ) {
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->orderRepository = $orderRepository;
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->mailService = $mailService;
        $this->documentService = $documentService;
        $this->documentRepository = $documentRepository;
        $this->apiVersionConverter = $apiVersionConverter;
        $this->stateMachineDefinition = $stateMachineDefinition;
        $this->orderService = $orderService;
    }

    /**
     * @Route("/api/v{version}/_action/order/{orderId}/state/{transition}", name="api.action.order.state_machine.order.transition_state", methods={"POST"})
     *
     * @throws OrderNotFoundException
     */
    public function orderStateTransition(
        string $orderId,
        string $transition,
        int $version,
        Request $request,
        Context $context
    ): JsonResponse {
        $mediaIds = $request->request->get('mediaIds', []);
        $documentIds = $request->request->get('documentIds', []);
        $stateFieldName = $request->query->get('stateFieldName', 'stateId');

        $stateMachineStates = $this->stateMachineRegistry->transition(
            new Transition(
                'order',
                $orderId,
                $transition,
                $stateFieldName
            ),
            $context
        );

        $toPlace = $stateMachineStates->get('toPlace');
        $fromPlace = $stateMachineStates->get('fromPlace');

        if ($toPlace) {
            $orderCriteria = $this->getOrderCriteria($orderId);
            /** @var OrderEntity|null $order */
            $order = $this->orderRepository->search($orderCriteria, $context)->first();
            if ($order === null) {
                throw new OrderNotFoundException($orderId);
            }

            $technicalName = 'order.state.' . $toPlace->getTechnicalName();

            $mailTemplate = $this->getMailTemplate($context, $technicalName, $order);

            if ($mailTemplate === null) {
                return new JsonResponse($toPlace);
            }

            $this->sendMail(
                $context,
                $mailTemplate,
                $order,
                $mediaIds,
                $documentIds,
                $toPlace,
                $fromPlace
            );
        }

        return new JsonResponse($this->apiVersionConverter->convertEntity(
            $this->stateMachineDefinition,
            $toPlace,
            $version
        ));
    }

    /**
     * @Route("/api/v{version}/_action/order_transaction/{orderTransactionId}/state/{transition}", name="api.action.order.state_machine.order_transaction.transition_state", methods={"POST"})
     *
     * @throws OrderNotFoundException
     */
    public function orderTransactionStateTransition(
        string $orderTransactionId,
        string $transition,
        int $version,
        Request $request,
        Context $context
    ): JsonResponse {
        $mediaIds = $request->request->get('mediaIds', []);
        $documentIds = $request->request->get('documentIds', []);
        $stateFieldName = $request->query->get('stateFieldName', 'stateId');

        $stateMachineStates = $this->stateMachineRegistry->transition(
            new Transition(
                'order_transaction',
                $orderTransactionId,
                $transition,
                $stateFieldName
            ),
            $context
        );

        $toPlace = $stateMachineStates->get('toPlace');
        $fromPlace = $stateMachineStates->get('fromPlace');

        if ($toPlace) {
            $orderCriteria = $this->getOrderCriteria();
            $orderCriteria->addFilter(new EqualsFilter('transactions.id', $orderTransactionId));
            /** @var OrderEntity|null $order */
            $order = $this->orderRepository->search($orderCriteria, $context)->first();
            if ($order === null) {
                throw new OrderNotFoundException('with transactionId: ' . $orderTransactionId);
            }

            $technicalName = 'order_transaction.state.' . $toPlace->getTechnicalName();

            $mailTemplate = $this->getMailTemplate($context, $technicalName, $order);

            if ($mailTemplate === null) {
                return new JsonResponse($toPlace);
            }

            $this->sendMail(
                $context,
                $mailTemplate,
                $order,
                $mediaIds,
                $documentIds,
                $toPlace,
                $fromPlace
            );
        }

        return new JsonResponse($this->apiVersionConverter->convertEntity(
            $this->stateMachineDefinition,
            $toPlace,
            $version
        ));
    }

    /**
     * @Route("/api/v{version}/_action/order_delivery/{orderDeliveryId}/state/{transition}", name="api.action.order.state_machine.order_delivery.transition_state", methods={"POST"})
     *
     * @throws OrderNotFoundException
     */
    public function orderDeliveryStateTransition(
        string $orderDeliveryId,
        string $transition,
        int $version,
        Request $request,
        Context $context
    ): JsonResponse {
        $mediaIds = $request->request->get('mediaIds', []);
        $documentIds = $request->request->get('documentIds', []);
        $stateFieldName = $request->query->get('stateFieldName', 'stateId');

        $stateMachineStates = $this->stateMachineRegistry->transition(
            new Transition(
                'order_delivery',
                $orderDeliveryId,
                $transition,
                $stateFieldName
            ),
            $context
        );

        $toPlace = $stateMachineStates->get('toPlace');
        $fromPlace = $stateMachineStates->get('fromPlace');

        if ($toPlace) {
            $orderCriteria = $this->getOrderCriteria();
            $orderCriteria->addFilter(new EqualsFilter('deliveries.id', $orderDeliveryId));
            /** @var OrderEntity|null $order */
            $order = $this->orderRepository->search($orderCriteria, $context)->first();
            if ($order === null) {
                throw new OrderNotFoundException('with deliveryId: ' . $orderDeliveryId);
            }

            $technicalName = 'order_delivery.state.' . $toPlace->getTechnicalName();

            $mailTemplate = $this->getMailTemplate($context, $technicalName, $order);

            if ($mailTemplate === null) {
                return new JsonResponse($toPlace);
            }

            $this->sendMail(
                $context,
                $mailTemplate,
                $order,
                $mediaIds,
                $documentIds,
                $toPlace,
                $fromPlace
            );
        }

        return new JsonResponse($this->apiVersionConverter->convertEntity(
            $this->stateMachineDefinition,
            $toPlace,
            $version
        ));
    }

    private function getOrderCriteria(?string $orderId = null): Criteria
    {
        if ($orderId) {
            $orderCriteria = new Criteria([$orderId]);
        } else {
            $orderCriteria = new Criteria([]);
        }

        $orderCriteria->addAssociation('orderCustomer.salutation');
        $orderCriteria->addAssociation('stateMachineState');
        $orderCriteria->addAssociation('transactions');
        $orderCriteria->addAssociation('deliveries.shippingMethod');
        $orderCriteria->addAssociation('salesChannel');

        return $orderCriteria;
    }

    /**
     * @throws InvalidDocumentException
     */
    private function getDocument(string $documentId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $documentId));
        $criteria->addAssociation('documentMediaFile');
        $criteria->addAssociation('documentType');

        /** @var DocumentEntity|null $documentEntity */
        $documentEntity = $this->documentRepository->search($criteria, $context)->get($documentId);

        if ($documentEntity === null) {
            throw new InvalidDocumentException($documentId);
        }

        $document = $this->documentService->getDocument($documentEntity, $context);

        return [
            'content' => $document->getFileBlob(),
            'fileName' => $document->getFilename(),
            'mimeType' => $document->getContentType(),
        ];
    }

    /**
     * @param string[] $mediaIds
     * @param string[] $documentIds
     */
    private function sendMail(
        Context $context,
        MailTemplateEntity $mailTemplate,
        OrderEntity $order,
        array $mediaIds,
        array $documentIds,
        StateMachineStateEntity $toPlace,
        ?StateMachineStateEntity $fromPlace
    ): void {
        $customer = $order->getOrderCustomer();
        if ($customer === null) {
            return;
        }

        $data = new DataBag();
        $data->set(
            'recipients',
            [
                $customer->getEmail() => $customer->getFirstName() . ' ' . $customer->getLastName(),
            ]
        );
        $data->set('senderName', $mailTemplate->getSenderName());
        $data->set('salesChannelId', $order->getSalesChannelId());

        $data->set('templateId', $mailTemplate->getId());
        $data->set('customFields', $mailTemplate->getCustomFields());
        $data->set('contentHtml', $mailTemplate->getContentHtml());
        $data->set('contentPlain', $mailTemplate->getContentPlain());
        $data->set('subject', $mailTemplate->getSubject());
        if ($mediaIds) {
            $data->set('mediaIds', $mediaIds);
        }

        $attachments = [];
        foreach ($mailTemplate->getMedia() as $mailTemplateMedia) {
            if (empty($mailTemplateMedia->getMedia())) {
                continue;
            }
            if ($mailTemplateMedia->getLanguageId() !== $context->getLanguageId()) {
                continue;
            }

            $attachments[] = $this->orderService->getAttachment($mailTemplateMedia->getMedia(), $context);
        }

        foreach ($documentIds as $documentId) {
            $attachments[] = $this->getDocument($documentId, $context);
        }

        if (!empty($attachments)) {
            $data->set('binAttachments', $attachments);
        }

        $this->mailService->send(
            $data->all(),
            $context,
            [
                'order' => $order,
                'previousState' => $fromPlace,
                'newState' => $toPlace,
                'salesChannel' => $order->getSalesChannel(),
            ]
        );

        $documents = [];
        foreach ($documentIds as $documentId) {
            $documents[] = $this->documentRepository->update(
                [
                    [
                        'id' => $documentId,
                        'sent' => true,
                    ],
                ],
                $context
            );
        }
    }

    private function getMailTemplate(Context $context, string $technicalName, OrderEntity $order): ?MailTemplateEntity
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media.media');
        $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', $technicalName));
        $criteria->setLimit(1);

        if ($order->getSalesChannelId()) {
            $criteria->addFilter(
                new EqualsFilter('mail_template.salesChannels.salesChannel.id', $order->getSalesChannelId())
            );
        }

        /** @var MailTemplateEntity|null $mailTemplate */
        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->first();

        return $mailTemplate;
    }
}
