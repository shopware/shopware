<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Event\DocumentGeneratorCriteriaEvent;
use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class StornoRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'storno';

    private DocumentConfigLoader $documentConfigLoader;

    private EventDispatcherInterface $eventDispatcher;

    private DocumentTemplateRenderer $documentTemplateRenderer;

    private string $rootDir;

    private EntityRepositoryInterface $orderRepository;

    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;

    private Connection $connection;

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        DocumentConfigLoader $documentConfigLoader,
        EventDispatcherInterface $eventDispatcher,
        DocumentTemplateRenderer $documentTemplateRenderer,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        Connection $connection,
        string $rootDir
    ) {
        $this->documentConfigLoader = $documentConfigLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->documentTemplateRenderer = $documentTemplateRenderer;
        $this->rootDir = $rootDir;
        $this->orderRepository = $orderRepository;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->connection = $connection;
    }

    public function supports(): string
    {
        return self::TYPE;
    }

    public function render(array $operations, Context $context, string $deepLinkCode = ''): array
    {
        $template = '@Framework/documents/storno.html.twig';

        $criteria = new Criteria();

        // TODO: future implementation (only fetch required data and associations)

        $this->eventDispatcher->dispatch(new DocumentGeneratorCriteriaEvent(self::TYPE, $operations, $criteria, $context));

        $orders = $this->fetchOrders($this->orderRepository, $operations, $criteria, $context, $deepLinkCode);

        $rendered = [];

        foreach ($orders as $order) {
            $operation = $operations[$order->getId()] ?? null;

            if ($operation === null) {
                continue;
            }

            $order = $this->handlePrices($order);

            $config = clone $this->documentConfigLoader->load(self::TYPE, $order->getSalesChannelId(), $context);

            $config->merge($operation->getConfig());

            $number = $config->getDocumentNumber();

            if (empty($number)) {
                $number = $this->numberRangeValueGenerator->getValue(
                    'document_' . self::TYPE,
                    $context,
                    $order->getSalesChannelId(),
                    $operation->isPreview()
                );
            }

            if (!empty($operation->getConfig()['custom']['invoiceNumber'])) {
                $referenceDocumentNumber = $operation->getConfig()['custom']['invoiceNumber'];
            } else {
                $invoice = $this->getReferenceInvoice($this->connection, $operation);

                if (empty($invoice)) {
                    throw new DocumentGenerationException('Can not generate storno document because no invoice document exists. OrderId: ' . $operation->getOrderId());
                }

                $documentRefer = json_decode($invoice['config'], true, 512, \JSON_THROW_ON_ERROR);

                $operation->setReferencedDocumentId($invoice['id']);

                $referenceDocumentNumber = $documentRefer['documentNumber'];
            }

            $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            $config->merge([
                'documentDate' => $operation->getConfig()['documentDate'] ?? $now,
                'documentNumber' => $number,
                'custom' => [
                    'stornoNumber' => $number,
                    'invoiceNumber' => $referenceDocumentNumber,
                ],
            ]);

            /** @var LocaleEntity $locale */
            $locale = $order->getLanguage()->getLocale();
            $html = $operation->isStatic() ? '' : $this->documentTemplateRenderer->render(
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

            $rendered[$order->getId()] = $doc;
        }

        return $rendered;
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        throw new DecorationPatternException(self::class);
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
}
