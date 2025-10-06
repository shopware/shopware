<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Event\DocumentOrderCriteriaEvent;
use Shopware\Core\Checkout\Document\Event\InvoiceOrdersEvent;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\DocumentFileRendererRegistry;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
final class InvoiceRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'invoice';

    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly DocumentConfigLoader $documentConfigLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly Connection $connection,
        private readonly DocumentFileRendererRegistry $fileRendererRegistry,
        private readonly ValidatorInterface $validator,
        private readonly FilesystemOperator $privateFilesystem,
    ) {
    }

    public function supports(): string
    {
        return self::TYPE;
    }

    public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        $result = new RendererResult();

        $template = '@Framework/documents/invoice.html.twig';

        $ids = \array_map(fn (DocumentGenerateOperation $operation) => $operation->getOrderId(), $operations);

        if (empty($ids)) {
            return $result;
        }

        $languageIdChain = $context->getLanguageIdChain();

        $chunk = $this->getOrdersLanguageId(array_values($ids), $context->getVersionId(), $this->connection);

        foreach ($chunk as ['language_id' => $languageId, 'ids' => $chunkIds]) {
            $criteria = OrderDocumentCriteriaFactory::create(\explode(',', (string) $chunkIds), $rendererConfig->deepLinkCode, self::TYPE);

            $context = $context->assign([
                'languageIdChain' => \array_values(\array_unique(\array_filter([$languageId, ...$languageIdChain]))),
            ]);

            $this->eventDispatcher->dispatch(new DocumentOrderCriteriaEvent(
                $criteria,
                $context,
                $operations,
                $rendererConfig,
                self::TYPE,
            ));

            $orders = $this->orderRepository->search($criteria, $context)->getEntities();

            $this->eventDispatcher->dispatch(new InvoiceOrdersEvent($orders, $context, $operations));

            foreach ($orders as $order) {
                $orderId = $order->getId();

                try {
                    if (!\array_key_exists($orderId, $operations)) {
                        continue;
                    }

                    /** @var DocumentGenerateOperation $operation */
                    $operation = $operations[$orderId];

                    $forceDocumentCreation = $operation->getConfig()['forceDocumentCreation'] ?? true;
                    if (!$forceDocumentCreation && $order->getDocuments()?->first()) {
                        continue;
                    }

                    $config = clone $this->documentConfigLoader->load(self::TYPE, $order->getSalesChannelId(), $context);

                    $config->merge($operation->getConfig());

                    $number = $config->getDocumentNumber() ?: $this->getNumber($context, $order, $operation);

                    $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

                    $config->merge([
                        'documentDate' => $operation->getConfig()['documentDate'] ?? $now,
                        'documentNumber' => $number,
                        'intraCommunityDelivery' => $this->isAllowIntraCommunityDelivery(
                            $config->jsonSerialize(),
                            $order,
                        ) && $this->isValidVat($order, $this->validator),
                        'custom' => [
                            'invoiceNumber' => $number,
                        ],
                    ]);

                    // create version of order to ensure the document stays the same even if the order changes
                    $operation->setOrderVersionId($this->orderRepository->createVersion($orderId, $context, 'document'));

                    if ($operation->isStatic()) {
                        $doc = new RenderedDocument($number, $config->buildName(), $operation->getFileType(), $config->jsonSerialize());
                        $result->addSuccess($orderId, $doc);

                        continue;
                    }

                    $language = $order->getLanguage();
                    if ($language === null) {
                        throw DocumentException::generationError('Can not generate credit note document because no language exists. OrderId: ' . $operation->getOrderId());
                    }

                    $doc = new RenderedDocument(
                        $number,
                        $config->buildName(),
                        $operation->getFileType(),
                        $config->jsonSerialize(),
                    );

                    $doc->setTemplate($template);
                    $doc->setOrder($order);
                    $doc->setContext($context);

                    try {
                        $debugDirectory = 'document_debug';
                        $orderCustomer = $order->getOrderCustomer();
                        $billingAddress = $order->getAddresses()?->get($order->getBillingAddressId());
                        $country = $billingAddress?->getCountry();
                        $countryTranslated = $country?->getTranslated();
                        $configSerialized = $config->jsonSerialize();

                        $debugData = [
                            'order' => [
                                'orderId' => $order->getId(),
                                'orderNumber' => $order->getOrderNumber(),
                                'currencyId' => $order->getCurrencyId(),
                                'language' => [
                                    'id' => $order->getLanguage()?->getId(),
                                    'code' => $order->getLanguage()?->getLocale()?->getCode(),
                                ],
                                'billingAddressId' => $order->getBillingAddressId(),
                            ],
                            'orderCustomer' => $orderCustomer ? [
                                'customerId' => $orderCustomer->getId() ?? null,
                                'customerNumber' => $orderCustomer->getCustomerNumber() ?? null,
                                'email' => $orderCustomer->getEmail() ?? null,
                                'title' => $orderCustomer->getTitle() ?? null,
                                'firstName' => $orderCustomer->getFirstName() ?? null,
                                'lastName' => $orderCustomer->getLastName() ?? null,
                            ] : null,
                            'context' => [
                                'languageId' => $context->getLanguageId(),
                                'languageIdChain' => $context->getLanguageIdChain(),
                            ],
                            'config' => [
                                'documentNumber' => $configSerialized['documentNumber'] ?? null,
                                'documentDate' => $configSerialized['documentDate'] ?? null,
                                'intraCommunityDelivery' => $configSerialized['intraCommunityDelivery'] ?? null,
                                'displayLineItems' => $configSerialized['displayLineItems'] ?? null,
                                'displayLineItemPosition' => $configSerialized['displayLineItemPosition'] ?? null,
                                'itemsPerPage' => $configSerialized['itemsPerPage'] ?? null,
                                'displayCompanyAddress' => $configSerialized['displayCompanyAddress'] ?? null,
                                'displayReturnAddress' => $configSerialized['displayReturnAddress'] ?? null,
                                'deliveryCountries' => $configSerialized['deliveryCountries'] ?? null,
                            ],
                            'billingAddress' => $billingAddress ? [
                                'id' => $billingAddress->getId(),
                                'firstname' => $billingAddress->getFirstName(),
                                'lastname' => $billingAddress->getLastName(),
                                'company' => $billingAddress->getCompany(),
                                'street' => $billingAddress->getStreet(),
                                'zipcode' => $billingAddress->getZipcode(),
                                'city' => $billingAddress->getCity(),
                                'country' => $country ? [
                                    'id' => $country->getId(),
                                    'iso' => $country->getIso(),
                                    'name' => $country->getName(),
                                    'getAddressFormat' => $country->getAddressFormat(),
                                    'getTranslated' => $countryTranslated ? [
                                        'name' => $countryTranslated['name'] ?? null,
                                        'addressFormat' => $countryTranslated['addressFormat'] ?? null,
                                    ] : null,
                                ] : null,
                                'additionalAddressLine1' => $billingAddress->getAdditionalAddressLine1(),
                                'additionalAddressLine2' => $billingAddress->getAdditionalAddressLine2(),
                                'department' => $billingAddress->getDepartment(),
                                'vatId' => $billingAddress->getVatId(),
                            ] : null,
                        ];

                        if (!$this->privateFilesystem->directoryExists($debugDirectory)) {
                            $this->privateFilesystem->createDirectory($debugDirectory);
                        }

                        $filename = \sprintf(
                            '%s/%s.log',
                            $debugDirectory,
                            (new \DateTime())->format('Ymd-His-u')
                        );

                        $this->privateFilesystem->write(
                            $filename,
                            var_export(\json_decode(\json_encode($debugData, \JSON_THROW_ON_ERROR)), true),
                        );
                    } catch (\Throwable $e) {
                        // do nothing
                    }

                    $doc->setContent($this->fileRendererRegistry->render($doc));

                    $result->addSuccess($orderId, $doc);
                } catch (\Throwable $exception) {
                    $result->addError($orderId, $exception);
                }
            }
        }

        return $result;
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        throw new DecorationPatternException(self::class);
    }

    private function getNumber(Context $context, OrderEntity $order, DocumentGenerateOperation $operation): string
    {
        return $this->numberRangeValueGenerator->getValue(
            'document_' . self::TYPE,
            $context,
            $order->getSalesChannelId(),
            $operation->isPreview()
        );
    }
}
