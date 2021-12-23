# Data Loading scripting services reference

## services.repository (`Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`)

The `repository` service allows you to query data, that is stored inside shopware.
Keep in mind that your app needs to have the correct permissions for the data it queries through this service.

### search()

The `search()` method allows you to search for Entities that match a given criteria.


#### Arguments

##### `entityName`: string


The name of the Entity you want to search for, e.g. `product` or `media`.

##### `criteria`: array


The criteria used for your search.


#### Return value

**Type**: `Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult`

A `EntitySearchResult` including all entities that matched your criteria.

### ids()

The `ids()` method allows you to search for the Ids of Entities that match a given criteria.


#### Arguments

##### `entityName`: string


The name of the Entity you want to search for, e.g. `product` or `media`.

##### `criteria`: array


The criteria used for your search.


#### Return value

**Type**: `Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult`

A `IdSearchResult` including all entity-ids that matched your criteria.

### aggregate()

The `aggregate()` method allows you to execute aggregations specified in the given criteria.


#### Arguments

##### `entityName`: string


The name of the Entity you want to aggregate data on, e.g. `product` or `media`.

##### `criteria`: array


The criteria that define your aggregations.


#### Return value

**Type**: `Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection`

A `AggregationResultCollection` including the results of the aggregations you specified in the criteria.



## services.store (`Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`)

The `store` service can be used to access publicly available `store-api` data.
As the data is publicly available your app does not need any additional permissions to use this service,
however querying data and also loading associations is restricted to the entities that are also available through the `store-api`.

Notice that the returned entities are already processed for the storefront,
this means that e.g. product prices are already calculated based on the current context.

### search()

The `search()` method allows you to search for Entities that match a given criteria.


#### Arguments

##### `entityName`: string


The name of the Entity you want to search for, e.g. `product` or `media`.

##### `criteria`: array


The criteria used for your search.


#### Return value

**Type**: `Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult`

A `EntitySearchResult` including all entities that matched your criteria.

### ids()

The `ids()` method allows you to search for the Ids of Entities that match a given criteria.


#### Arguments

##### `entityName`: string


The name of the Entity you want to search for, e.g. `product` or `media`.

##### `criteria`: array


The criteria used for your search.


#### Return value

**Type**: `Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult`

A `IdSearchResult` including all entity-ids that matched your criteria.

### aggregate()

The `aggregate()` method allows you to execute aggregations specified in the given criteria.


#### Arguments

##### `entityName`: string


The name of the Entity you want to aggregate data on, e.g. `product` or `media`.

##### `criteria`: array


The criteria that define your aggregations.


#### Return value

**Type**: `Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection`

A `AggregationResultCollection` including the results of the aggregations you specified in the criteria.



