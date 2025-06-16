---
title: Implement measurement system  
date: 2025-05-14  
area: inventory  
tags: [measurement system]
---

## Context

We want to provide merchants with the ability to define the measurement system for their products—e.g., metric or imperial.

This configuration should be available per sales channel domain or per product.

We also need to offer a convenient way to display or persist preferred units in the storefront, administration, and APIs.

## Decision

We will introduce two measurement systems: Metric and Imperial. The default will be Metric, with millimeters for length and kilograms for weight, matching the current system.

When users or store API consumers fetch products, the system will resolve the configured measurement system and units based on the sales channel domain. These configured units will be used to display product measurements in the storefront or in store API responses.

Consumers can override the returned measurement units by setting preferred units in the request headers.

## Technical Details

### Resolving Sales Channel Domain Measurement System Configuration

We will expose a `MeasurementUnits` DTO in the `SalesChannelContext` (`SalesChannelContext.measurementSystem`), allowing quick look up of current configured product measurements of given context.

```php
class MeasurementUnits extends Struct
{
    public function __construct(public readonly string $system, public array $units)
    {
    }

    public function addMeasurementType(string $type, string $unit): void
    {
        $this->units[$type] = $unit;
    }

    public function setUnit(string $type, string $unit): void
    {
        if (\array_key_exists($type, $this->units)) {
            throw MeasurementSystemException::unsupportedMeasurementUnit($unit, array_keys($this->units));
        }

        $this->units[$type] = $unit;
    }
    
    public function getUnit(string $type): string
    {
        if (!\array_key_exists($type, $this->units)) {
            throw MeasurementSystemException::unsupportedMeasurementType($type, array_keys($this->units));
        }

        return $this->units[$type];
    }
}

// By default, only weight and length units are supported
$measurementSystem = new MeasurementUnits('metric', [
    'lengthUnit' => 'mm',
    'weightUnit' => 'kg',
]);

$salesChannelContext->setMeasurementSystem($measurementSystem);
```

The `MeasurementUnits` will be initialized in `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory::create` based on current sales channel domain

The measurement configuration will be visible in `/store-api/context`:

```json
{
  "token": "<context_token>",
  ...,
  "measurementUnits": {
    "system": "metric",
    "units": {
      "length": "mm",
      "weight": "kg",
      // added by externals
      "volume": "m3"
    }
  },
  "apiAlias": "sales_channel_context"
}
```

### Runtime Field for Product Measurement Units

Measurement units will be dynamically converted based on the configured measurement system and units for the sales channel domain. If no domain is specified, it will fall back to the default sales channel units.

The `measurementUnits` is a runtime-calculated field based on the product’s measurement values and selected units.

```php
namespace Shopware\Core\Content\Product\Subscriber;

class ProductSubscriber implements EventSubscriberInterface 
{
    public function salesChannelLoaded(SalesChannelEntityLoadedEvent $event): void
    {        
        foreach ($event->getEntities() as $product) {
            $product->assign([
                'measurementUnits' => $this->productMeasurementBuilder->build($product, $event->getSalesChannelContext()),
            ]);
        }
    }
}
```

The `ProductPackageMeasurementBuilder` will convert product's default packaging measurements to the configured measurement system and units.

```php
/**
 * @internal
 */
class ProductPackageMeasurementBuilder
{
    public function __construct(
        private readonly MeasurementUnitConverter $unitConverter
    ) {}
    
    public function build(SalesChannelProductEntity $product, SalesChannelContext $context): MeasurementUnits
    {
        $measurementUnit = new MeasurementUnits();
        
        $lengthUnit = $context->getMeasurementSystem()->getUnit('length');
        $weightUnit = $context->getMeasurementSystem()->getUnit('weight');
        
        $measurementUnit->add('width', $this->unitConverter->convert($product->getWidth(), 'mm', $lengthUnit));
        $measurementUnit->add('height', $this->unitConverter->convert($product->getHeight(), 'mm', $lengthUnit));
        $measurementUnit->add('length', $this->unitConverter->convert($product->getLength(), 'mm', $lengthUnit));
        $measurementUnit->add('weight', $this->unitConverter->convert($product->getWeight(), 'kg', $weightUnit));
                
        return $measurementUnit;
    }
}
```

Internal or external services can listen to `SalesChannelEntityLoadedEvent` to inject additional fields into the product’s measurement units.

Example product output:

```json
{
  ...other product fields,
  "width": 150,
  "length": 120,
  "height": 200,
  "weight": 1.2,
  "measurementUnits": {
    "width": {
      "value": 1.5,
      "unit": "m"
    },
    "length": {
      "value": 1.2,
      "unit": "m"
    },
    "height": {
      "value": 2.0,
      "unit": "m"
    },
    "weight": {
      "value": 1.2,
      "unit": "kg"
    },
    // added by externals
    "volume": {
      "value": 1.2,
      "unit": "m3"
    },
    "customFields.fooField": {
      "value": 1.2,
      "unit": "m"
    }
  }
}
```

We will also introduce a `MeasurementUnitConverter` to handle unit conversion.

```php
abstract class AbstractMeasurementUnitConverter
{
    public function convert(float $value, string $fromUnit = 'mm', string $toUnit = 'in', float $precision = 3): ConvertedUnit;
}
```

### Overriding Product measurement Units via API Headers

By default, product measurement units are stored in the metric system. Two new request headers allow overriding the default units, useful for external services:

- `sw-measurement-length-unit`: overrides the default length unit
- `sw-measurement-weight-unit`: overrides the default weight unit

When reading product measurements, we check these headers and convert values accordingly. If not provided, we fall back to the configured system.

```php
class ProductSubscriber implements EventSubscriberInterface 
{
    public function productLoaded(SalesChannelEntityLoadedEvent $event): void
    {        
        $lengthUnit = $request->headers->get('sw-measurement-length-unit', 'mm');
        $weightUnit = $request->headers->get('sw-measurement-weight-unit', 'kg');

        foreach ($event->getEntities() as $product) {
            $product->setWidth($this->unitConverter->convert($product->getWidth(), 'mm', $lengthUnit));
            $product->setHeight($this->unitConverter->convert($product->getHeight(), 'mm', $lengthUnit));
            $product->setLength($this->unitConverter->convert($product->getLength(), 'mm', $lengthUnit));
            $product->setWeight($this->unitConverter->convert($product->getWeight(), 'kg', $weightUnit));
        }
    }
}
```

The same logic applied for searching products in the API.

When saving product measurements, values will be converted back to the default (metric) units before persisting to the database.

This enables API consumers to use their preferred units without worrying about converting values manually.

**Note**: Converted values are only available in API requests and responses. The stored product measurements will always use the metric system to avoid inconsistencies.

### Storefront Integration

Storefront templates currently use fixed units (mm, kg):

```twig
{{ product.width }} mm
{{ product.weight }} kg
```

With the new system, the value and unit will be dynamically resolved based on the configured measurement system and units.

```twig
{{ product.measurements.type('width').value }} {{ product.measurements.type('width').unit }}
{{ product.measurements.type('weight').value }} {{ product.measurements.type('weight').unit }}
```

#### New Twig Filters for On-the-Fly Conversion

We provide new Twig filters for on-the-fly unit conversion.

```twig
{# Convert to domain-configured units (e.g., m and g) #}
{{ 1500|sw_convert_unit(from: 'mm') }}  {# Output: 1.5m #}
{{ 1.2|sw_convert_unit(from: 'kg') }}   {# Output: 1200g #}

{# Convert to specific units #}
{{ 100|sw_convert_unit(from: 'kg', to: 'lb') }}  {# Output: 220.462 #}
{# Convert to with specific rounding (default as 2) #}
{{ 100|sw_convert_unit(from: 'kg', to: 'lb', precision: 1) }}  {# Output: 220.5 #}
```

## Consequences

- Provides flexibility for merchants and API consumers to work with preferred units, enhancing usability and customization.
- Increases complexity due to runtime fields, conversion logic, and additional request headers.
- Needs to be careful not to introduce performance overhead for large datasets or high-traffic APIs.
- API consumers might need to adapt their implementation to leverage the new system.

### Backward Compatibility

- No existing functionality will break, as the default units (mm and kg) remain unchanged.
