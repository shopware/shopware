[titleEn]: <>(SalesChannel Entity definition)

We impemented a central way to define entities which should be available over the `sales-channel-api`.

An EntityDefinition can now define a decoration definition for the `sales-channel-api`:


```php
<?php
// ...
class ProductDefinition extends EntityDefinition
{
	public static function getEntityName(): string
	{
		return 'product';
	}
	
	public static function getSalesChannelDecorationDefinition()
	{
		return SalesChannelProductDefinition::class;
	}
}
```

### Declaring the SalesChannelDefinition

A decorating sales channel definition for an entity should extend the original definition class.

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

class SalesChannelProductDefinition 
	extends ProductDefinition 
	implements SalesChannelDefinitionInterface
{
	use SalesChannelDefinitionTrait;
}
```

These declaration allows to replace different functionalities for an entity:

* Rewriting the storage - getEntityName()
  * Example usage: I want to denormalize my entities in a different table for better performance
* Rewriting the DTO classes - getEntityClass getEntityCollection
  * Example usage: I want to provide some helper functions or more properties in the storefront
* Adding or removing some fields - defineFields
  * Example usage: I can add more fields for an entity which will be displayed in a storefront or in other clients

### Association Handling

It is import to override the defineFields function to rewrite association fields with their sales channel decoration definition:

```php
protected static function defineFields(): FieldCollection
{
	$fields = parent::defineFields();
	
	self::decorateDefinitions($fields);
	
	return $fields;
}
```

This decoration call replaces all entity definition classes of the defined association fields with the decorated definition.

### Basic filters

Additionally by implementing the `\Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface` interface the developer has the opportunity to add some basic filters if the entity will be fetched for a sales channel:

```php
public static function processCriteria(
	Criteria $criteria, 
	SalesChannelContext $context
) : void {
	$criteria->addFilter(
		new EqualsFilter('product.active', true)
	);
}
```

### Reigster the definition

Like the EntityDefinition classes the Definition classes for sales channel entities has to be registered over the Dependency Injection Container by tagging the definition with the `shopware.sales_channel.entity.definition` tag.

```xml
<service 
		id="Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition">

<tag name="shopware.sales_channel.entity.definition" entity="product"/>

</service>
```

### Repository registration

Like the entity repository for the DAL, the each registered sales channel entity definition gets an own registered repository. The repository is registered like the original entity definition but with an additional `sales_channel.` prefix:

Example: `sales_channel.product.repository`

The registered class is an instance of `\Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository`.

This repository provides only a read functions:

```php
public function search(
	Criteria $criteria, 
	SalesChannelContext $context
) : EntitySearchResult

public function aggregate(
	Criteria $criteria, 
	SalesChannelContext $context
) : AggregatorResult

public function searchIds(
	Criteria $criteria, 
	SalesChannelContext $context
) : IdSearchResult
```

### Api Routes

All registered sales channel definitions has registered api routes: (example for product)

```php
sales-channel-api.product.detail
	/sales-channel-api/v{version}/product/{id}

sales-channel-api.product.search-ids
	/sales-channel-api/v{version}/search-ids/product

sales-channel-api.product.search
	/sales-channel-api/v{version}/product
```

### Event Registration

Entities which loaded for a sales channel has own events. All Events of the Data Abstraction Layer are prefixed with sales_channel. (Example for product):

* `sales_channel.product.loaded`
* `sales_channel.search.result.loaded`
* `sales_channel.product.id.search.result.loaded`
* `sales_channel.product.aggregation.result.loaded`
