<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentException;
use Shopware\Core\Checkout\Order\Exception\PaymentMethodNotAvailableException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Service\MailServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

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
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var StateMachineRegistry
     */
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
     * @var EntityRepositoryInterface
     */
    private $documentRepository;

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
    private $salesChannelRepository;

    /**
     * @param ValidationServiceInterface|DataValidationFactoryInterface $orderValidationFactory
     */
    public function __construct(
        DataValidator $dataValidator,
        $orderValidationFactory,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService,
        EntityRepositoryInterface $paymentMethodRepository,
        StateMachineRegistry $stateMachineRegistry,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $mailTemplateRepository,
        EntityRepositoryInterface $documentRepository,
        MailServiceInterface $mailService,
        DocumentService $documentService,
        EntityRepositoryInterface $salesChannelRepository
    ) {
        $this->dataValidator = $dataValidator;
        $this->orderValidationFactory = $orderValidationFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->orderRepository = $orderRepository;
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->documentRepository = $documentRepository;
        $this->mailService = $mailService;
        $this->documentService = $documentService;
        $this->salesChannelRepository = $salesChannelRepository;
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

        $this->validateCart($cart, $context->getContext());

        return $this->cartService->order($cart, $context);
    }

    /**
     * @internal Should not be called from outside the core
     */
    public function orderStateTransition(
        string $orderId,
        string $transition,
        ParameterBag $data,
        Context $context,
        ?string $customerId = null
    ): StateMachineStateEntity {
        $mediaIds = $data->get('mediaIds', []);
        $documentIds = $data->get('documentIds', []);
        $stateFieldName = $data->get('stateFieldName', 'stateId');

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
            $orderCriteria = $this->getOrderCriteria($orderId, $customerId);
            /** @var OrderEntity|null $order */
            $order = $this->orderRepository->search($orderCriteria, $context)->first();
            if ($order === null) {
                throw new OrderNotFoundException($orderId);
            }

            $technicalName = 'order.state.' . $toPlace->getTechnicalName();

            $mailTemplate = $this->getMailTemplate($context, $technicalName, $order);

            if ($mailTemplate !== null) {
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
        }

        return $toPlace;
    }

    /**
     * @internal Should not be called from outside the core
     */
    public function orderTransactionStateTransition(
        string $orderTransactionId,
        string $transition,
        ParameterBag $data,
        Context $context
    ): StateMachineStateEntity {
        $mediaIds = $data->get('mediaIds', []);
        $documentIds = $data->get('documentIds', []);
        $stateFieldName = $data->get('stateFieldName', 'stateId');

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

            if ($mailTemplate !== null) {
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
        }

        return $toPlace;
    }

    /**
     * @internal Should not be called from outside the core
     */
    public function orderDeliveryStateTransition(
        string $orderDeliveryId,
        string $transition,
        ParameterBag $data,
        Context $context
    ): StateMachineStateEntity {
        $mediaIds = $data->get('mediaIds', []);
        $documentIds = $data->get('documentIds', []);
        $stateFieldName = $data->get('stateFieldName', 'stateId');

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

            if ($mailTemplate !== null) {
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
        }

        return $toPlace;
    }

    private function validateCart(Cart $cart, Context $context): void
    {
        $idsOfPaymentMethods = [];

        foreach ($cart->getTransactions() as $paymentMethod) {
            $idsOfPaymentMethods[] = $paymentMethod->getPaymentMethodId();
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('active', true)
        );

        $paymentMethods = $this->paymentMethodRepository->searchIds($criteria, $context);

        if ($paymentMethods->getTotal() !== count(array_unique($idsOfPaymentMethods))) {
            foreach ($cart->getTransactions() as $paymentMethod) {
                if (!in_array($paymentMethod->getPaymentMethodId(), $paymentMethods->getIds(), true)) {
                    throw new PaymentMethodNotAvailableException($paymentMethod->getPaymentMethodId());
                }
            }
        }
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateOrderData(ParameterBag $data, SalesChannelContext $context): void
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

    private function getOrderCriteria(?string $orderId = null, ?string $customerId = null): Criteria
    {
        if ($orderId) {
            $orderCriteria = new Criteria([$orderId]);
        } else {
            $orderCriteria = new Criteria([]);
        }

        if ($customerId !== null) {
            $orderCriteria->addFilter(
                new EqualsFilter('order.orderCustomer.customerId', $customerId)
            );
        }

        $orderCriteria->addAssociation('orderCustomer.salutation');
        $orderCriteria->addAssociation('stateMachineState');
        $orderCriteria->addAssociation('transactions');
        $orderCriteria->addAssociation('deliveries.shippingMethod');
        $orderCriteria->addAssociation('salesChannel');

        return $orderCriteria;
    }

    private function getMailTemplate(Context $context, string $technicalName, OrderEntity $order): ?MailTemplateEntity
    {
        $criteria = new Criteria();
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

        $data = new ParameterBag();
        $data->set(
            'recipients',
            [
                $customer->getEmail() => $customer->getFirstName() . ' ' . $customer->getLastName(),
            ]
        );
        $data->set('senderName', $mailTemplate->getSenderName());
        $data->set('salesChannelId', $order->getSalesChannelId());

        $data->set('contentHtml', $mailTemplate->getContentHtml());
        $data->set('contentPlain', $mailTemplate->getContentPlain());
        $data->set('subject', $mailTemplate->getSubject());
        if ($mediaIds) {
            $data->set('mediaIds', $mediaIds);
        }

        $documents = [];
        foreach ($documentIds as $documentId) {
            $documents[] = $this->getDocument($documentId, $context);
        }

        if (!empty($documents)) {
            $data->set('binAttachments', $documents);
        }

        // getting the correct sales channel domain with the help of the languageId of the order
        $languageId = $order->getLanguageId();
        $salesChannelCriteria = new Criteria([$order->getSalesChannel()->getId()]);
        $salesChannelCriteria->getAssociation('domains')
            ->addFilter(
                new EqualsFilter('languageId', $languageId)
            );

        $salesChannel = $this->salesChannelRepository->search($salesChannelCriteria, $context)->first();

        $this->mailService->send(
            $data->all(),
            $context,
            [
                'order' => $order,
                'previousState' => $fromPlace,
                'newState' => $toPlace,
                'salesChannel' => $salesChannel,
            ]
        );

        $writes = array_map(static function ($id) {
            return ['id' => $id, 'sent' => true];
        }, $documentIds);

        if (!empty($writes)) {
            $this->documentRepository->update($writes, $context);
        }
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
}
