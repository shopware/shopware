<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\Event\DeliveryNoteOrdersEvent;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class DeliveryNoteRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'delivery_note';

    private DocumentConfigLoader $documentConfigLoader;

    private EventDispatcherInterface $eventDispatcher;

    private DocumentTemplateRenderer $documentTemplateRenderer;

    private string $rootDir;

    private EntityRepositoryInterface $orderRepository;

    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;

    /**
     * @internal
     */
    public function __construct(
        EntityRepositoryInterface $orderRepository,
        DocumentConfigLoader $documentConfigLoader,
        EventDispatcherInterface $eventDispatcher,
        DocumentTemplateRenderer $documentTemplateRenderer,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        string $rootDir
    ) {
        $this->documentConfigLoader = $documentConfigLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->documentTemplateRenderer = $documentTemplateRenderer;
        $this->rootDir = $rootDir;
        $this->orderRepository = $orderRepository;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
    }

    public function supports(): string
    {
        return self::TYPE;
    }

    public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        $result = new RendererResult();

        $template = '@Framework/documents/delivery_note.html.twig';

        $ids = \array_map(function (DocumentGenerateOperation $operation) {
            return $operation->getOrderId();
        }, $operations);

        if (empty($ids)) {
            return $result;
        }

        $criteria = OrderDocumentCriteriaFactory::create($ids, $rendererConfig->deepLinkCode);

        // TODO: future implementation (only fetch required data and associations)

        /** @var OrderCollection $orders */
        $orders = $this->orderRepository->search($criteria, $context)->getEntities();

        $this->eventDispatcher->dispatch(new DeliveryNoteOrdersEvent($orders, $context));

        foreach ($orders as $order) {
            $orderId = $order->getId();

            try {
                if (!\array_key_exists($order->getId(), $operations)) {
                    continue;
                }

                /** @var DocumentGenerateOperation $operation */
                $operation = $operations[$order->getId()];

                $config = clone $this->documentConfigLoader->load(self::TYPE, $order->getSalesChannelId(), $context);

                $config->merge($operation->getConfig());

                $number = $config->getDocumentNumber() ?: $this->getNumber($context, $order, $operation);

                $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
                $customConfig = $operation->getConfig()['custom'] ?? [];

                $config->merge([
                    'documentNumber' => $number,
                    'documentDate' => $operation->getConfig()['documentDate'] ?? $now,
                    'custom' => [
                        'deliveryNoteNumber' => $number,
                        'deliveryDate' => $customConfig['deliveryDate'] ?? $now,
                        'deliveryNoteDate' => $customConfig['deliveryNoteDate'] ?? $now,
                    ],
                ]);

                if ($operation->isStatic()) {
                    $doc = new RenderedDocument('', $number, $config->buildName(), $operation->getFileType(), $config->jsonSerialize());
                    $result->addSuccess($orderId, $doc);

                    continue;
                }

                $deliveries = null;
                if ($order->getDeliveries()) {
                    $deliveries = $order->getDeliveries()->first();
                }

                /** @var LocaleEntity $locale */
                $locale = $order->getLanguage()->getLocale();

                $html = $this->documentTemplateRenderer->render(
                    $template,
                    [
                        'order' => $order,
                        'orderDelivery' => $deliveries,
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
