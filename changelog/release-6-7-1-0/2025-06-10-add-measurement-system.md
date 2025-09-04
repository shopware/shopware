---
title: Add measurement system
issue: 7225
---
# Core
* Added a new migration `Migration1742199548MeasurementSystem` to create measurement_system and measurement_display_unit tables.
* Added new column `measurement_units` to the `sales_channel` and `sales_channel_domain` tables
* Added system_config `core.measurementUnits.system` and `core.measurementUnits.units` to configure the default measurement system and display unit.
* Added new sub folder `src/Core/Content/MeasurementSystem` to hold the measurement system entities and services.
* Added new `\Shopware\Core\Content\MeasurementSystem\Field\MeasurementUnitsField` as a new DAL field and its corresponding serializer `\Shopware\Core\Content\MeasurementSystem\Field\MeasurementUnitsFieldSerializer` to handle measurement units in the DAL of sales channel and sales channel domain entities.
* Added `\Shopware\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementUnitBuilder` to build product measurement units depending on the configured measurement system of the context.
* Added `\Shopware\Core\Content\MeasurementSystem\UnitConverter\MeasurementUnitConverter` to convert measurement units between different measurement units
* Added `\Shopware\Core\Content\MeasurementSystem\UnitProvider\MeasurementUnitProvider` to fetch available measurement units info
* Changed `\Shopware\Core\Content\Product\Subscriber\ProductSubscriber::salesChannelLoaded` to build the product measurement units based on the configured measurement system of the sales channel.
* Added new constant `HEADER_MEASUREMENT_LENGTH_UNIT` and `HEADER_MEASUREMENT_WEIGHT_UNIT` in `\Shopware\Core\PlatformRequest`
* Changed `\Shopware\Core\Content\Product\Subscriber\ProductSubscriber::loaded` to convert product's measurement units when request's header contains `HEADER_MEASUREMENT_LENGTH_UNIT` or `HEADER_MEASUREMENT_WEIGHT_UNIT`
* Added `\Shopware\Core\Content\Product\Subscriber\ProductSubscriber::beforeWriteProduct` to convert product's measurement units before writing to the database when request's header contains `HEADER_MEASUREMENT_LENGTH_UNIT` or `HEADER_MEASUREMENT_WEIGHT_UNIT`
* Added new twig filter `sw_convert_unit` in `\Shopware\Core\Content\MeasurementSystem\TwigExtension\MeasurementConvertUnitTwigFilter` to convert measurement units in twig templates
___
# API
* Added new request header `sw-measurement-weight-unit` and `sw-measurement-length-unit` to allow clients to specify the measurement units for length and weight when reading or writing product's measurement units.
___
# Storefront
* Changed the template `Resources/views/storefront/component/buy-widget/buy-widget.html.twig` to display the product's measurement units in the configured measurement units of current sales channel instead of fixed kg/mm units
* Changed the template `Resources/views/storefront/component/product/feature/types/feature-attribute.html.twig` to display the product's measurement units in the configured measurement units of current sales channel instead of fixed kg/mm units
___
# Upgrade Information

## Measurement system units info are now provided in the store-api

Previously, the store-api did not provide measurement system units info. The product's measurement units were always returned in fixed units (kg/mm).

Now, it provides the measurement system units info in the response of the `context` endpoint and `product` API endpoints depending on the configured measurement system of the sales channel domain.

This allows the clients to render the product's measurement units in the configured measurement units of the sales channel domain instead of fixed units (kg/mm).

_Note: The product's measurement units are still stored in the database in fixed units (kg/mm) and converted to the configured measurement units of the sales channel domain when reading or writing the product's measurement units._

### After:

## New Admin API's request headers

We added new request headers `sw-measurement-weight-unit` and `sw-measurement-length-unit` to allow clients to specify the measurement units for length and weight when reading or writing product's measurement units.

This is useful when the user can provide measurement units in the header and get the desired product's measurement units in the response. And also the same when writing the product's measurement units in the desired measurement units without convert the units back and forth

## New twig filter to convert measurement units

For the storefront, we added a new twig filter `sw_convert_unit` to convert measurement units in twig templates. This allows the developers to convert measurement units in the templates without writing custom logic.

It allows the developers to convert measurement units of any value, any variable in the templates without writing custom logic. 

Or they can also convert between any measurement units by passing the desired measurement unit as a parameter to the filter.

### Example:

```twig
{{ product.customFields.fooInCm|sw_convert_unit(from:'cm') }} // Converts the value of custom field `fooInCm` from cm to the configured measurement unit of the sales channel domain

{{ product.customFields.fooInCm|sw_convert_unit(from:'cm', to:'inch') }} // Converts the value of custom field `fooInCm` from cm to inch

{{ product.weight|sw_convert_unit(from: 'kg', to: 'pound', precision: 1) }} // you can also specify the precision of the converted value
```