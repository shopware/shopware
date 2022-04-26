<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\Event\DocumentGeneratorCriteriaEvent;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class DeliveryNoteRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'delivery_note';

    private DocumentConfigLoader $documentConfigLoader;

    private EventDispatcherInterface $eventDispatcher;

    private DocumentTemplateRenderer $documentTemplateRenderer;

    private string $rootDir;

    private EntityRepositoryInterface $orderRepository;

    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;

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

    public function render(array $operations, Context $context, string $deepLinkCode = ''): array
    {
        $template = '@Framework/documents/delivery_note.html.twig';

        $criteria = new Criteria();

        // TODO: future implementation (only fetch required data and associations)

        $this->eventDispatcher->dispatch(new DocumentGeneratorCriteriaEvent(self::TYPE, $operations, $criteria, $context));

        $orders = $this->fetchOrders($this->orderRepository, $operations, $criteria, $context, $deepLinkCode);

        $rendered = [];

        foreach ($orders as $order) {
            if (!\array_key_exists($order->getId(), $operations)) {
                continue;
            }

            /** @var DocumentGenerateOperation $operation */
            $operation = $operations[$order->getId()];

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

            $deliveries = null;
            if ($order->getDeliveries()) {
                $deliveries = $order->getDeliveries()->first();
            }

            /** @var LocaleEntity $locale */
            $locale = $order->getLanguage()->getLocale();

            $html = $operation->isStatic() ? '' : $this->documentTemplateRenderer->render(
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

            $rendered[$order->getId()] = $doc;
        }

        return $rendered;
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        throw new DecorationPatternException(self::class);
    }
}
