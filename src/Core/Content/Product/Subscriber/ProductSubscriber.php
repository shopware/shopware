<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
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
            if (!$product instanceof ProductEntity && !$product instanceof PartialEntity) {
                continue;
            }

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

            $payload = $command->getPayload();

            if ($command->hasField('width') && \is_float($payload['width']) && $lengthUnitHeader) {
                $command->addPayload('width', $this->measurementUnitConverter->convert(
                    $payload['width'],
                    $lengthUnitHeader,
                    MeasurementUnits::DEFAULT_LENGTH_UNIT,
                )->value);
            }

            if ($command->hasField('height') && \is_float($payload['height']) && $lengthUnitHeader) {
                $command->addPayload('height', $this->measurementUnitConverter->convert(
                    $payload['height'],
                    $lengthUnitHeader,
                    MeasurementUnits::DEFAULT_LENGTH_UNIT,
                )->value);
            }

            if ($command->hasField('length') && \is_float($payload['length']) && $lengthUnitHeader) {
                $command->addPayload('length', $this->measurementUnitConverter->convert(
                    $payload['length'],
                    $lengthUnitHeader,
                    MeasurementUnits::DEFAULT_LENGTH_UNIT,
                )->value);
            }

            if ($command->hasField('weight') && \is_float($payload['weight']) && $weightUnitHeader) {
                $command->addPayload('weight', $this->measurementUnitConverter->convert(
                    $payload['weight'],
                    $weightUnitHeader,
                    MeasurementUnits::DEFAULT_WEIGHT_UNIT,
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
            $height = $product->get('height');
            $width = $product->get('width');
            $length = $product->get('length');

            if (\is_float($height) && $height > 0) {
                $assigns['height'] = $this->measurementUnitConverter->convert($height, MeasurementUnits::DEFAULT_LENGTH_UNIT, $lengthUnitHeader)->value;
            }

            if (\is_float($width) && $width > 0) {
                $assigns['width'] = $this->measurementUnitConverter->convert($width, MeasurementUnits::DEFAULT_LENGTH_UNIT, $lengthUnitHeader)->value;
            }

            if (\is_float($length) && $length > 0) {
                $assigns['length'] = $this->measurementUnitConverter->convert($length, MeasurementUnits::DEFAULT_LENGTH_UNIT, $lengthUnitHeader)->value;
            }
        }

        $weightUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT);

        $weight = $product->get('weight');

        if ($weightUnitHeader && \is_float($weight) && $weight > 0) {
            $assigns['weight'] = $this->measurementUnitConverter->convert($product->get('weight'), MeasurementUnits::DEFAULT_WEIGHT_UNIT, $weightUnitHeader)->value;
        }

        if (!empty($assigns)) {
            $product->assign($assigns);
        }
    }
}
