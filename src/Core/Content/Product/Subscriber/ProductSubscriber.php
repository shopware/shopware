<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\MeasurementSystem\MeasurementSystemInfo;
use Shopware\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementUnitBuilder;
use Shopware\Core\Content\MeasurementSystem\UnitConverter\AbstractMeasurementUnitConverter;
use Shopware\Core\Content\Product\AbstractIsNewDetector;
use Shopware\Core\Content\Product\AbstractProductMaxPurchaseCalculator;
use Shopware\Core\Content\Product\AbstractProductVariationBuilder;
use Shopware\Core\Content\Product\AbstractPropertyGroupSorter;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('inventory')]
class ProductSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductVariationBuilder $productVariationBuilder,
        private readonly AbstractProductPriceCalculator $calculator,
        private readonly AbstractPropertyGroupSorter $propertyGroupSorter,
        private readonly AbstractProductMaxPurchaseCalculator $maxPurchaseCalculator,
        private readonly AbstractIsNewDetector $isNewDetector,
        private readonly SystemConfigService $systemConfigService,
        private readonly ProductMeasurementUnitBuilder $measurementUnitBuilder,
        private readonly AbstractMeasurementUnitConverter $measurementUnitConverter,
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_LOADED_EVENT => 'loaded',
            'product.partial_loaded' => 'loaded',
            'sales_channel.' . ProductEvents::PRODUCT_LOADED_EVENT => 'salesChannelLoaded',
            'sales_channel.product.partial_loaded' => 'salesChannelLoaded',
            EntityWriteEvent::class => 'beforeWriteProduct',
        ];
    }

    /**
     * @param EntityLoadedEvent<ProductEntity|PartialEntity> $event
     */
    public function loaded(EntityLoadedEvent $event): void
    {
        $isAdminSource = $event->getContext()->getSource() instanceof AdminApiSource;

        foreach ($event->getEntities() as $product) {
            if ($isAdminSource) {
                $this->convertMeasurementUnit($product);
            }

            $this->setDefaultLayout($product);

            $this->productVariationBuilder->build($product);
        }
    }

    /**
     * @param SalesChannelEntityLoadedEvent<ProductEntity|PartialEntity> $event
     */
    public function salesChannelLoaded(SalesChannelEntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $product) {
            $price = $product->get('cheapestPrice');

            if ($price instanceof CheapestPriceContainer) {
                $product->assign([
                    'cheapestPrice' => $price->resolve($event->getContext()),
                    'cheapestPriceContainer' => $price,
                ]);
            }

            $assigns = [];

            if (($properties = $product->get('properties')) !== null) {
                $assigns['sortedProperties'] = $this->propertyGroupSorter->sort($properties);
            }

            $assigns['calculatedMaxPurchase'] = $this->maxPurchaseCalculator->calculate($product, $event->getSalesChannelContext());

            $assigns['isNew'] = $this->isNewDetector->isNew($product, $event->getSalesChannelContext());

            $assigns['measurementUnits'] = $this->measurementUnitBuilder->build($product, $event->getSalesChannelContext());

            $product->assign($assigns);

            $this->setDefaultLayout($product, $event->getSalesChannelContext()->getSalesChannelId());

            $this->productVariationBuilder->build($product);
        }

        $this->calculator->calculate($event->getEntities(), $event->getSalesChannelContext());
    }

    public function beforeWriteProduct(EntityWriteEvent $event): void
    {
        $lengthUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT);
        $weightUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT);

        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== ProductDefinition::ENTITY_NAME) {
                continue;
            }

            if (!$lengthUnitHeader && !$weightUnitHeader) {
                continue;
            }

            if ($command->hasField('width') && $lengthUnitHeader) {
                $command->addPayload('width', $this->measurementUnitConverter->convert(
                    $command->getPayload()['width'],
                    $lengthUnitHeader,
                    MeasurementSystemInfo::DEFAULT_LENGTH_UNIT,
                )->value);
            }

            if ($command->hasField('height') && $lengthUnitHeader) {
                $command->addPayload('height', $this->measurementUnitConverter->convert(
                    $command->getPayload()['height'],
                    $lengthUnitHeader,
                    MeasurementSystemInfo::DEFAULT_LENGTH_UNIT,
                )->value);
            }

            if ($command->hasField('length') && $lengthUnitHeader) {
                $command->addPayload('length', $this->measurementUnitConverter->convert(
                    $command->getPayload()['length'],
                    $lengthUnitHeader,
                    MeasurementSystemInfo::DEFAULT_LENGTH_UNIT,
                )->value);
            }

            if ($command->hasField('weight') && $weightUnitHeader) {
                $command->addPayload('weight', $this->measurementUnitConverter->convert(
                    $command->getPayload()['weight'],
                    $weightUnitHeader,
                    MeasurementSystemInfo::DEFAULT_WEIGHT_UNIT,
                )->value);
            }
        }
    }

    /**
     * @param Entity $product - typehint as Entity because it could be a ProductEntity or PartialEntity
     */
    private function setDefaultLayout(Entity $product, ?string $salesChannelId = null): void
    {
        if (!$product->has('cmsPageId')) {
            return;
        }

        if ($product->get('cmsPageId') !== null) {
            return;
        }

        $cmsPageId = $this->systemConfigService->get(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $salesChannelId);

        if (!$cmsPageId) {
            return;
        }

        $product->assign(['cmsPageId' => $cmsPageId]);
    }

    private function convertMeasurementUnit(ProductEntity|PartialEntity $product): void
    {
        $lengthUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT);

        $assigns = [];

        if ($lengthUnitHeader) {
            if (\is_float($product->get('height')) && $product->get('height') > 0) {
                $assigns['height'] = $this->measurementUnitConverter->convert($product->get('height'), MeasurementSystemInfo::DEFAULT_LENGTH_UNIT, $lengthUnitHeader)->value;
            }

            if (\is_float($product->get('width')) && $product->get('width') > 0) {
                $assigns['width'] = $this->measurementUnitConverter->convert($product->get('width'), MeasurementSystemInfo::DEFAULT_LENGTH_UNIT, $lengthUnitHeader)->value;
            }

            if (\is_float($product->get('length')) && $product->get('length') > 0) {
                $assigns['length'] = $this->measurementUnitConverter->convert($product->get('length'), MeasurementSystemInfo::DEFAULT_LENGTH_UNIT, $lengthUnitHeader)->value;
            }
        }

        $weightUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT);

        if ($weightUnitHeader && \is_float($product->get('weight')) && $product->get('weight') > 0) {
            $assigns['weight'] = $this->measurementUnitConverter->convert($product->get('weight'), MeasurementSystemInfo::DEFAULT_WEIGHT_UNIT, $weightUnitHeader)->value;
        }

        if (!empty($assigns)) {
            $product->assign($assigns);
        }
    }
}
