<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdBuilder;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdInvoiceOrdersEvent;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class ZugferdRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'zugferd_invoice';

    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        protected EntityRepository $orderRepository,
        protected Connection $connection,
        protected ZugferdBuilder $documentBuilder,
        protected EventDispatcherInterface $eventDispatcher,
        protected DocumentConfigLoader $documentConfigLoader,
        protected NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
    ) {
    }

    public function supports(): string
    {
        return self::TYPE;
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        throw new DecorationPatternException(self::class);
    }

    public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        $result = new RendererResult();

        $ids = \array_map(static fn (DocumentGenerateOperation $operation) => $operation->getOrderId(), $operations);
        if (empty($ids)) {
            return $result;
        }

        $languageIdChain = $context->getLanguageIdChain();

        $chunk = $this->getOrdersLanguageId(array_values($ids), $context->getVersionId(), $this->connection);
        foreach ($chunk as ['language_id' => $languageId, 'ids' => $ids]) {
            $criteria = OrderDocumentCriteriaFactory::create(\explode(',', (string) $ids), $rendererConfig->deepLinkCode);
            $criteria->addAssociation('lineItems.product.manufacturer');

            $context->assign([
                'languageIdChain' => \array_values(\array_unique(\array_filter([$languageId, ...$languageIdChain]))),
            ]);

            $orders = $this->orderRepository->search($criteria, $context)->getEntities();

            $this->eventDispatcher->dispatch(new ZugferdInvoiceOrdersEvent($orders, $context, $operations));

            foreach ($orders as $order) {
                if ($operations[$order->getId()] instanceof DocumentGenerateOperation) {
                    $this->createDocument($result, $order, $operations[$order->getId()], $context);
                }
            }
        }

        return $result;
    }

    protected function createDocument(RendererResult $renderResult, OrderEntity $order, DocumentGenerateOperation $operation, Context $context): void
    {
        $forceDocumentCreation = $operation->getConfig()['forceDocumentCreation'] ?? true;
        if (!$forceDocumentCreation && $order->getDocuments()?->first()) {
            return;
        }

        $config = clone $this->documentConfigLoader->load(InvoiceRenderer::TYPE, $order->getSalesChannelId(), $context);
        $config->merge($operation->getConfig());
        // So no A11y will be generated
        $config->merge(['fileTypes' => ['xml']]);

        $documentNumber = $config->getDocumentNumber();
        if ($documentNumber === null) {
            $config->setDocumentNumber($documentNumber = $this->getNumber($context, $order, $operation));
        }

        try {
            $content = $this->documentBuilder->buildDocument($order, $config, $context);
            $renderResult->addSuccess(
                $order->getId(),
                new RenderedDocument(
                    $documentNumber,
                    $config->buildName(),
                    FileTypes::XML,
                    $config->jsonSerialize(),
                    'application/xml',
                    $content
                )
            );
        } catch (DocumentException $e) {
            $renderResult->addError($order->getId(), $e);
        }
    }

    private function getNumber(Context $context, OrderEntity $order, DocumentGenerateOperation $operation): string
    {
        return $this->numberRangeValueGenerator->getValue(
            'document_' . InvoiceRenderer::TYPE,
            $context,
            $order->getSalesChannelId(),
            $operation->isPreview()
        );
    }
}
