<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Event\InvoiceOrdersEvent;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
final class InvoiceRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'invoice';

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly DocumentConfigLoader $documentConfigLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DocumentTemplateRenderer $documentTemplateRenderer,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
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

        $template = '@Framework/documents/invoice.html.twig';

        $ids = \array_map(fn (DocumentGenerateOperation $operation) => $operation->getOrderId(), $operations);

        if (empty($ids)) {
            return $result;
        }

        $chunk = $this->getOrdersLanguageId(array_values($ids), $context->getVersionId(), $this->connection);

        foreach ($chunk as ['language_id' => $languageId, 'ids' => $ids]) {
            $criteria = OrderDocumentCriteriaFactory::create(explode(',', (string) $ids), $rendererConfig->deepLinkCode);
            $context = $context->assign([
                'languageIdChain' => array_values(array_unique(array_filter([$languageId, ...$context->getLanguageIdChain()]))),
            ]);

            // TODO: future implementation (only fetch required data and associations)

            /** @var OrderCollection $orders */
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

                    $config = clone $this->documentConfigLoader->load(self::TYPE, $order->getSalesChannelId(), $context);

                    $config->merge($operation->getConfig());

                    $number = $config->getDocumentNumber() ?: $this->getNumber($context, $order, $operation);

                    $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

                    $config->merge([
                        'documentDate' => $operation->getConfig()['documentDate'] ?? $now,
                        'documentNumber' => $number,
                        'intraCommunityDelivery' => $this->isAllowIntraCommunityDelivery(
                            $operation->getConfig(),
                            $order->getDeliveries()->first()
                        ),
                        'custom' => [
                            'invoiceNumber' => $number,
                        ],
                    ]);

                    // create version of order to ensure the document stays the same even if the order changes
                    $operation->setOrderVersionId($this->orderRepository->createVersion($orderId, $context, 'document'));

                    if ($operation->isStatic()) {
                        $doc = new RenderedDocument('', $number, $config->buildName(), $operation->getFileType(), $config->jsonSerialize());
                        $result->addSuccess($orderId, $doc);

                        continue;
                    }

                    /** @var LanguageEntity|null $language */
                    $language = $order->getLanguage();
                    if ($language === null) {
                        throw DocumentException::generationError('Can not generate credit note document because no language exists. OrderId: ' . $operation->getOrderId());
                    }

                    /** @var LocaleEntity $locale */
                    $locale = $language->getLocale();

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

    private function isAllowIntraCommunityDelivery(array $config, OrderDeliveryEntity|null $orderDelivery = null): bool
    {
        if (empty($config['displayAdditionalNoteDelivery']) || empty($config['deliveryCountries'])) {
            return false;
        }

        if (!$orderDelivery) {
            return false;
        }

        /** @var OrderAddressEntity $shippingAddress */
        $shippingAddress = $orderDelivery->getShippingOrderAddress();

        $country = $shippingAddress->getCountry();

        if (!$country) {
            return false;
        }

        $isCompanyTaxFree = $country->getCompanyTax()->getEnabled();

        return $isCompanyTaxFree && \in_array($country->getId(), $config['deliveryCountries'], true);
    }
}
