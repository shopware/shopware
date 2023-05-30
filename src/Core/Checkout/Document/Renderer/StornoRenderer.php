<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Event\StornoOrdersEvent;
use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('customer-order')]
final class StornoRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'storno';

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly DocumentConfigLoader $documentConfigLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DocumentTemplateRenderer $documentTemplateRenderer,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly ReferenceInvoiceLoader $referenceInvoiceLoader,
        private readonly string $rootDir,
        private readonly Connection $connection
    ) {
    }

    public function supports(): string
    {
        return self::TYPE;
    }

    public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        $result = new RendererResult();

        $template = '@Framework/documents/storno.html.twig';

        $ids = \array_map(fn (DocumentGenerateOperation $operation) => $operation->getOrderId(), $operations);

        if (empty($ids)) {
            return $result;
        }

        $referenceInvoiceNumbers = [];

        $orders = new OrderCollection();

        /** @var DocumentGenerateOperation $operation */
        foreach ($operations as $operation) {
            try {
                $orderId = $operation->getOrderId();
                $invoice = $this->referenceInvoiceLoader->load($orderId, $operation->getReferencedDocumentId(), $rendererConfig->deepLinkCode);

                if (empty($invoice)) {
                    throw new DocumentGenerationException('Can not generate storno document because no invoice document exists. OrderId: ' . $operation->getOrderId());
                }

                $documentRefer = json_decode((string) $invoice['config'], true, 512, \JSON_THROW_ON_ERROR);

                $referenceInvoiceNumbers[$orderId] = $documentRefer['documentNumber'];

                $order = $this->getOrder($orderId, $invoice['orderVersionId'], $context, $rendererConfig->deepLinkCode);

                $orders->add($order);
                $operation->setReferencedDocumentId($invoice['id']);
                if ($order->getVersionId()) {
                    $operation->setOrderVersionId($order->getVersionId());
                }
            } catch (\Throwable $exception) {
                $result->addError($operation->getOrderId(), $exception);
            }
        }

        // TODO: future implementation (only fetch required data and associations)

        $this->eventDispatcher->dispatch(new StornoOrdersEvent($orders, $context, $operations));

        foreach ($orders as $order) {
            $orderId = $order->getId();

            try {
                $operation = $operations[$orderId] ?? null;

                if ($operation === null) {
                    continue;
                }

                $order = $this->handlePrices($order);

                $config = clone $this->documentConfigLoader->load(self::TYPE, $order->getSalesChannelId(), $context);

                $config->merge($operation->getConfig());

                $number = $config->getDocumentNumber() ?: $this->getNumber($context, $order, $operation);

                $referenceDocumentNumber = $referenceInvoiceNumbers[$operation->getOrderId()];

                $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

                $config->merge([
                    'documentDate' => $operation->getConfig()['documentDate'] ?? $now,
                    'documentNumber' => $number,
                    'custom' => [
                        'stornoNumber' => $number,
                        'invoiceNumber' => $referenceDocumentNumber,
                    ],
                ]);

                if ($operation->isStatic()) {
                    $doc = new RenderedDocument('', $number, $config->buildName(), $operation->getFileType(), $config->jsonSerialize());
                    $result->addSuccess($orderId, $doc);

                    continue;
                }

                /** @var LocaleEntity $locale */
                $locale = $order->getLanguage()->getLocale();
                $html = $this->documentTemplateRenderer->render(
                    $template,
                    [
                        'order' => $order,
                        'config' => $config,
                        'rootDir' => $this->rootDir,
                        'context' => $context,
                    ],
                    $context,
                    $order->getSalesChannelId(),
                    $order->getLanguageId(),
                    $locale->getCode()
                );

                $doc = new RenderedDocument(
                    $html,
                    $number,
                    $config->buildName(),
                    $operation->getFileType(),
                    $config->jsonSerialize(),
                );

                $result->addSuccess($orderId, $doc);
            } catch (\Throwable $exception) {
                $result->addError($orderId, $exception);
            }
        }

        return $result;
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        throw new DecorationPatternException(self::class);
    }

    private function getOrder(string $orderId, string $versionId, Context $context, string $deepLinkCode = ''): OrderEntity
    {
        ['language_id' => $languageId] = $this->getOrdersLanguageId([$orderId], $versionId, $this->connection)[0];

        // Get the correct order with versioning from reference invoice
        $versionContext = $context->createWithVersionId($versionId)->assign([
            'languageIdChain' => array_unique(array_filter([$languageId, $context->getLanguageId()])),
        ]);

        $criteria = OrderDocumentCriteriaFactory::create([$orderId], $deepLinkCode);

        /** @var ?OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $versionContext)->get($orderId);

        if ($order === null) {
            throw new InvalidOrderException($orderId);
        }

        return $order;
    }

    private function handlePrices(OrderEntity $order): OrderEntity
    {
        foreach ($order->getLineItems() ?? [] as $lineItem) {
            $lineItem->setUnitPrice($lineItem->getUnitPrice() / -1);
            $lineItem->setTotalPrice($lineItem->getTotalPrice() / -1);
        }

        foreach ($order->getPrice()->getCalculatedTaxes()->sortByTax()->getElements() as $tax) {
            $tax->setTax($tax->getTax() / -1);
        }

        $order->setShippingTotal($order->getShippingTotal() / -1);
        $order->setAmountNet($order->getAmountNet() / -1);
        $order->setAmountTotal($order->getAmountTotal() / -1);

        return $order;
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
