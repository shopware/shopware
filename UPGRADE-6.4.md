# 6.4.0.0
## Confirm checkout page / account edit order page
- On the `confirm checkout page` and `account edit order page`, the modal to change the payment or shipping method was removed.
- Instead, a maximum of `5` per default payment and shipping methods will be shown instantly.
- All other methods will be hidden under a JavaScript controlled collapse and may be triggered to appear by user interaction.
## Minimum PHP version increased
The minimum required PHP version for Shopware 6.4.0.0 is now PHP 7.4.
Please make sure, that your system has at least this PHP version activated.
## Currency filter
The default of the currency filter in the administration changed.
It will now display by default 2 fraction digits and up to 20, if available.
### Before
* value is 15.123456 -> output is 15.12
* value is 15.12345678913245 -> output is 15.12
### After
* value is 15.123456 -> output is 15.123456
* value is 15.12345678913245 -> output is 15.12345678913245
In case you've been creating empty feature sets using the current faulty behaviour, please make sure to at least include
a name for any new feature set from now on.
System and plugin configurations made with `config.xml` can now have less than 4
characters as configuration key but are not allowed anymore to start with a
number.
## Changed the loading of storefront SCSS files in plugins

Previously all Storefront relevant SCSS files (`*.scss`) of a plugin have automatically been loaded and compiled by shopware when placed inside the directory `src/Resources/app/storefront/src/scss`.
Because all SCSS files have been loaded automatically it could have let to inconsistent results when dealing with custom SCSS variables in separate files for example.

This behaviour has been changed and now only a single entry file will be used by plugins which is the `YourPlugin/src/Resources/app/storefront/src/scss/base.scss`.

### Before

All the SCSS files in this example directory have been loaded automatically:

```
└── scss
    ├── custom-component.scss
    ├── footer.scss
    ├── header.scss
    ├── product-detail.scss
    └── variables.scss
```

### After

Now you need a `base.scss` and need to load all other files from there using the `@import` rule:

```
└── scss
    ├── base.scss <-- This is now mandatory and loads all other files
    ├── custom-component.scss
    ├── footer.scss
    ├── header.scss
    ├── product-detail.scss
    └── variables.scss
```

The `base.scss` for the previous example directory would look like this in order to load all SCSS properly:

```scss
// Content of the base.scss
@import 'variables';
@import 'header';
@import 'product-detail';
@import 'custom-component';
```
## prepare the exchange of Swift_Mailer with Symfony/Mailer in 6.4.0
We will exchange the current default mailer `Swift_Mailer` with the `Symfony\Mailer` in 6.4.0.
If this concerns your own code changes, you can already implement your changes behind this feature flag to minimize your work on the release of the 6.4.0. Please refer to [feature flag handling on docs.shopware.com](https://docs.shopware.com/en/shopware-platform-dev-en/references-internals/core/feature-flag-handling) about the handling of feature flags.
## context.salesChannel.countries removed
Previously, the sales channel object in the context contained all countries assigned to the sales channel. This data has now been removed. The access via `$context->getSalesChannel()->getCountries()` therefore no longer returns the previous result.
To load the countries of a sales channel, the class `\Shopware\Core\System\Country\SalesChannel\CountryRoute` should be used.
If you're using the `api.custom.store.download` route, be aware that its behaviour will change when `platform` >=
v6.4.0.0  is in use. The route will no longer trigger a plugin update. 
In case you'd like to trigger a plugin update, you'll need to dispatch another request to the
`api.action.plugin.update` route.
## Migration system changes

We've changed the migration system to add the following features:

### 1. Defining migrations that are released in the next major

We're switching to a trunk based development in combination with feature flags. New breaking features that are intended 
for the next major are also developed on the trunk. To reduce the chance of unintended breaks in minor and patch releases, 
we should not execute the migration for new major features until its necessary. This also allows us to change the migration if necessary.

### 2. Defining destructive changes that can automatically and safely be executed in accordance with blue-green deployment

Currently, it's impossible to define destructive changes in a sane way.

An Example:
- create migration that adds a new column `newData` which replaces `oldData`
- add a trigger in this migration, which synchronizes the data in the columns, to make the change blue-green compatible
- the old field is deprecated for the next major

Problems:
1. When do we remove the deprecated column/trigger? It's not safe to remove them with the next major, because it prevents a rollback and is not blue-green compatible. 
   The first safe option would be the first update from major to the next minor.
2. It's not possible to define destructive changes in the same migration. Currently, these migrations have to be created manually, after it's safe to execute.

### Migration system upgrade guide

We've grouped the migrations into major versions. By default, all non-destructive migrations are executed up to the current major. 
In contrast, all destructive migrations are executed up to a "safe" point. This can be configured with the mode.

There are three possible values for the mode:
1. `--version-selection-mode=safe`: Execute only "safe" destructive changes. This means only migrations from the penultimate major are executed. 
   So with the update to 6.5.0.0 all destructive changes in 6.3 or lower are executed.
2. `--version-selection-mode=blue-green`: Execute as early as possible, but still blue-green compatible. 
   This means with the update to 6.4.1.0 from 6.4.0.0 all destructive changes in 6.3 or lower are executed.
3. `--version-selection-mode=all`: Execute all destructive changes up to the current major.

To allow this selection, we've moved all migrations from `\Shopware\Core\Framework\Migration\MigrationSource.core` 
into `\Shopware\Core\Framework\Migration\MigrationSource.core.V6_3`. `core` is now empty by default. You can still extend it. 
The execution order is now like this:
1. `core.V6_3`
2. `core.V6_4`
3. newer majors...
4. `core`

This means all new migrations need to be created in the matching major folder. Currently, this is `src/Core/Migration/V6_3`, 
it will be `src/Core/Migration/V6_4` soon. To keep the backwards compatibility, Migrations still need to be defined in `src/Core/Migration`. 
To accomplish that, just create it in the versioned folder and create a class in the old folder that simply extends the other class without changing anything.  

The method `\Shopware\Core\Framework\Migration\MigrationCollectionLoader::collectAllForVersion` will return a collection with all "safe" `MigrationSource`s including `core`.

**bin/console database:migrate --all core**

Should do the sames as before. Alternatively, you can run it for each major:
- `bin/console database:migrate --all V6_3`
- `bin/console database:migrate --all V6_4`
- etc

**bin/console database:migrate-destructive --all core**

This behavior changed! By default, this will only executes "safe" destructive changes. It used to execute all destructive migrations.

To get the old behavior, run:

`bin/console database:migrate-destructive --all --version-selection-mode=all core`

To run the destructive migrations as early as possible but still blue-green, run:

`bin/console database:migrate-destructive --all --version-selection-mode=blue-green core`

**Changes to auto updater**

We've changed the updater to only execute safe destructive migrations. It used to execute **ALL** destructive changes.

## Creating core migrations

To allow implementing this feature with a feature flag, we've to create a legacy migration in `src/Core/Migraiton`, 
which simply extends from the real migration in `src/Core/Migration/$MAJOR`. All migrations have been changed in that way.
The `bin/console database:create-migration` command automatically creates a legacy migration.
Due to the way that `img`'s `object-fit` works, it is not possible to mimic the 'Auto' setting of the block background. This means that elements that currently have 'Auto' set as their background mode will look different.
## Cms entities version aware

### Plugin updates

This change update the primary key of `cms_page`, `cms_slot`, `cms_block` and `cms_section` and the corresponding translation tables. If your plugin incorporates foreign keys to these tables you will need to update your migrations and dal entity definitions.

Please use `bin/console dal:validate` to see if you have to adjust your plugins anywhere.

#### Update

If your plugin is already installed the shopware core migration will take care of adjusting the foreign key. A new column `{TABLE_NAME}_version_id` is created, and the constraint widened. You will just have to add a version reference field in your definitions.

For a `cms_page` relation this would make these lines mandatory in your field definition like this:

```php
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;

new ReferenceVersionField(CmsPageDefinition::class);
```

#### Install

If your plugin is newly installed you should add a combined foreign key to your create table statement.

```sql
CREATE TABLE _TABLE_ IF NOT EXISTS
    `cms_page_id` binary(16) DEFAULT NULL, # the existing column
    `cms_page_version_id` binary(16) NOT NULL',# from noe on mandatory
    [....]
    KEY `_NAME_` (`cms_page_id`,`cms_page_version_id`),
    CONSTRAINT `_NAME_` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE # notice the two column on two column key
);

```

### Deployment notice

Due to the migration changing the product table as well, the update process might be slower than usual.
## Removed deprecated columns

* Removed the column `currency`.`decimal_precision`. The rounding is now controlled by the `CashRoundingConfig` in the `SalesChannelContext`
* Removed the column `product`.`purchase_price`. Replaced by `product`.`purchase_prices`
* Removed the column `customer_wishlist_product`. This column was never used, and the feature still requires a feature flag.
## Storefront

### `CustomerEntity` is now required in account related methods and routes

If you need the `CustomerEntity` of the logged-in customer, just add the annotation `@LoginRequired()` 
and add a `CustomerEntity $customer` parameter to your controller action. 

### Routing changes

The accessibility of a route in maintenance mode, is now exclusively controlled by the request attribute `allow_maintenance`. 
You can use `defaults={"allow_maintenance"=true}` in your route definition.

Removed the parameter `swagShopId` from `StorefrontRenderEvent`, Use `appShopId` instead.


### System-Config

Removed default for `detail.showReviews`, use `core.listing.showReview` instead.

### Changed classes

The classes in `Shopware\Storefront\Page\Product\CrossSelling` moved into the core `Shopware\Core\Content\Product\SalesChannel\CrossSelling`

The class `\Shopware\Storefront\Page\Product\ProductLoader` was removed, use `\Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute` instead.

The methods  `StorefrontPluginConfigurationFactory::createPluginConfig` and `StorefrontPluginConfigurationFactory::createThemeConfig` 
were removed from public api, use `StorefrontPluginConfigurationFactory::createFromBundle` or `StorefrontPluginConfigurationFactory::createFromApp` instead. 

### API

We've removed the route `/api/_action/theme/{themeId}/fields` (`api.action.theme.fields`), use `/api/_action/theme/{themeId}/structured-field` (`api.action.theme.structuredFields`) instead.


## Core

* `ReadProtected` replaced by `ApiAware`, See [NEXT-13371 - Added api aware flag](../release-6-3-5-1/2021-01-25-added-api-aware-flag.md)
* `\Shopware\Core\Checkout\Customer\SalesChannel\AccountRegistrationService` replaced by `RegisterRoute` and `RegisterConfirmRoute`
* `CustomerEntity` is now required in account related routes


### Events

All events that are dispatched in a sales channel context now implement `ShopwareSalesChannelEvent`. The return type `getContext` may have changed from `SalesChannelContext`
to `Context`. To get the sales channel context, use `getSalesChannelContext`.

## Administration 

### Removed deprecated SCSS color variables

We've removed a lot of deprecated colors.

### Changed methods and events

* stopped emitting `paginate` event in `src/app/component/entity/sw-entity-listing/index.js`, use `page-change` instead.
* removed method `generateDocumentPreviewLink` in `src/core/service/api/document.api.service.js`
* removed method `onChangeDisplayNoteDelivery` in `src/module/sw-settings-document/page/sw-settings-document-detail/index.js`
* removed data `discardChanges` in `src/module/sw-category/page/sw-category-detail/index.js`
* removed method `generateDocumentLink` in `src/core/service/api/document.api.service.js` use `getDocument` instead

### Removed blocks

- `sw_cms_slot_component` replaced by `sw_cms_slot_content_component`
- `sw_cms_slot_preview_overlay` replaced by `sw_cms_slot_content_preview_overlay`
- `sw_cms_slot_overlay` replaced by `sw_cms_slot_content_overlay`
- `sw_cms_slot_overlay_content` replaced by `sw_cms_slot_content_overlay_content`
- `sw_cms_slot_overlay_action_settings` replaced by `sw_cms_slot_content_overlay_action_settings`
- `sw_cms_slot_overlay_action_swap` replaced by `sw_cms_slot_content_overlay_action_swap`
- `sw_cms_slot_settings_modal` replaced by `sw_cms_slot_content_settings_modal`
- `sw_cms_slot_settings_modal_component` replaced by `sw_cms_slot_content_settings_modal_component`
- `sw_cms_slot_settings_modal_footer` replaced by `sw_cms_slot_content_settings_modal_footer`
- `sw_cms_slot_settings_modal_action_confirm` replaced by `sw_cms_slot_content_settings_modal_action_confirm`
- `sw_cms_slot_element_modal` replaced by `sw_cms_slot_content_element_modal`
- `sw_cms_slot_element_modal_selection` replaced by `sw_cms_slot_content_element_modal_selection`
- `sw_cms_slot_element_modal_selection_element` replaced by `sw_cms_slot_content_element_modal_selection_element`
- `sw_cms_slot_element_modal_selection_element_component` replaced by `sw_cms_slot_content_element_modal_selection_element_component`
- `sw_cms_slot_element_modal_selection_element_overlay` replaced by `sw_cms_slot_content_element_modal_selection_element_overlay`
- `sw_cms_slot_element_modal_selection_element_label` replaced by `sw_cms_slot_content_element_modal_selection_element_label`
- `sw_cms_slot_element_modal_footer` replaced by `sw_cms_slot_content_element_modal_footer`
- `sw_cms_slot_element_modal_action_abort` replaced by `sw_cms_slot_content_element_modal_action_abort`
- `sw_cms_toolbar_slot_language_swtich` replaced by `sw_cms_toolbar_slot_language_switch`
Some database columns were renamed in the `customer` table to follow the `snake_case` naming convention.
The old database columns will be dropped in 6.5.0.0.

These changes only apply to hard-coded SQL (e.g. in Migrations). 
The DAL already works properly with the new fields.
The parameter signature of `src/Core/Framework/Api/OAuth/ClientRepository::getClientEntity` changed due to the major update of the oauth2-server dependency.
OAuth2-Clients should be validated separately in the new `validateClient` method.
See: https://github.com/thephpleague/oauth2-server/pull/938

The parameter signature of `src/Core/Checkout/Payment/Cart/Token/JWTFactoryV2` changed.
It uses the injected configuration object rather than a private key.

The parameter signature of `src/Core/Framework/Api/OAuth/BearerTokenValidator` changed.
The injected configuration object was added as parameter.
## Guzzle major version upgrade
We upgraded the guzzle dependency to a new major version v7. Please refer to the [guzzle upgrade guide](https://github.com/guzzle/guzzle/blob/master/UPGRADING.md#60-to-70) to make sure your plugins are compatible.
## Elasticsearch Refactoring

To improve the performance and reliability of Elasticsearch, we have decided to refactor Elasticsearch in the first iteration in the Storefront only for Product listing and Product searches.
This allows us to create a optimized Elasticsearch index with only required fields selected by an single sql to make the indexing fast as possible.
This also means for extensions, they need to extend the indexing to make their fields searchable.

Here is an simple decoration to add a new random field named `myNewField` to the index. 
For adding more information from the Database you should execute a single query with all document ids (`array_column($documents, 'id'')`) and map the values

```xml
<service id="MyDecorator" decorates="Shopware\Elasticsearch\Product\ElasticsearchProductDefinition">
    <argument type="service" id="MyDecorator.inner"/>
    <argument type="service" id="\Doctrine\DBAL\Connection"/>
</service>
```

```php
<?php

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\Indexing\EntityMapper;
use Doctrine\DBAL\Connection;

class MyDecorator extends AbstractElasticsearchDefinition
{
    private AbstractElasticsearchDefinition $productDefinition;
    private Connection $connection;

    public function __construct(AbstractElasticsearchDefinition $productDefinition, Connection $connection)
    {
        $this->productDefinition = $productDefinition;
        $this->connection = $connection;
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->productDefinition->getEntityDefinition();
    }

    public function getMapping(Context $context): array
    {
        $mapping = $this->productDefinition->getMapping($context);

        $mapping['properties']['myNewField'] = EntityMapper::INT_FIELD;

        // Adding nested field with id
        $mapping['properties']['myManyToManyAssociation'] = [
            'type' => 'nested',
            'properties' => [
                'id' => EntityMapper::KEYWORD_FIELD,
            ],
        ];

        return $mapping;
    }

    public function extendDocuments(array $documents, Context $context): array
    {
        $documents = $this->productDefinition->extendDocuments($documents, $context);
        $productIds = array_column($documents, 'id');

        $query = <<<SQL
SELECT LOWER(HEX(mytable.product_id)) as id, GROUP_CONCAT(LOWER(HEX(mytable.myFkField)) SEPARATOR "|") as relationIds
FROM mytable
WHERE
    mytable.product_id IN(:ids) AND
    mytable.product_version_id = :liveVersion
SQL;


        $associationData = $this->connection->fetchAllKeyValue(
            $query,
            [
                'ids' => Uuid::fromHexToBytesList($productIds),
                'liveVersion' => Defaults::LIVE_VERSION
            ],
            [
                'ids' => Connection::PARAM_STR_ARRAY
            ]
        );

        foreach ($documents as &$document) {
            // Normal field directly on the product
            $document['myNewField'] = random_int(PHP_INT_MIN, PHP_INT_MAX);

            // Nested object with an id field
            $document['myManyToManyAssociation'] = array_map(function (string $id) {
                return ['id' => $id];
            }, array_filter(explode('|', $associationData[$document['id']] ?? '')));
        }

        return $documents;
    }
}
```

When searching products make sure you add elasticsearch aware to your criteria to use Elasticsearch in background.

```php
$criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
$context = \Shopware\Core\Framework\Context::createDefaultContext();
// Enables elasticsearch for this search
$context->addState(\Shopware\Core\Framework\Context::STATE_ELASTICSEARCH_AWARE);

$repository->search($criteria, $context);
```
## LineItems rules behaviour changed
The rules for line items are now considering also nested line items.
Before the change, only the first level of line items was taken into account.
Check your rules, if they still take effect as intended.
## NPM package copy-webpack-plugin update
This plugin has now version `6.4.1`, take a look at the [github changelog](https://github.com/webpack-contrib/copy-webpack-plugin/releases/tag/v6.0.0) for breaking changes.

## NPM package node-sass replacement
Removed `node-sass` package because it is deprecated. Added the `sass` package as replacement. For more information take a look [deprecation page](https://sass-lang.com/blog/libsass-is-deprecated).
## Twig system config access
The `shopware.config` variable was removed. To access a system config value inside twig, use `config('my_config_key')`.

## Twig theme config access
The `shopware.theme` variable was removed. To access the theme config value inside twig, use `theme_config('my_config_key')`.

## Theme breakpoint config array
The `shopware.theme.breakpoint` config value is no more available, please use the corresponding sizes. If you need to restore the array, you can use the following code:
```
{% set breakpoint = {
    'xs': theme_config('breakpoint.sm'),
    'sm': theme_config('breakpoint.md'),
    'md': theme_config('breakpoint.lg'),
    'lg': theme_config('breakpoint.xl')
} %}
```
## Store api service
Deprecated method `downloadPlugin` if you're using it to install **and** update a plugin then use `downloadAndUpdatePlugin`.
In the future `downloadPlugin` will only download plugins.

## Removed plugin manager
The plugin manager in the administration is removed with all of its components and replaced by the `sw-extension` module.

UPGRADE FROM 6.3.x.x to 6.4
=======================

Table of contents
----------------

* [Core](#core)
* [Administration](#administration)
* [Storefront](#storefront)
* [Refactorings](#refactorings)

Core
----

* Implementations of `\Shopware\Core\Framework\Api\Sync\SyncServiceInterface::sync` need to change the type of the first argument `$operations` to `iterable`.

Administration
--------------
* `StateDeprecated` needs to be replaced with `State`
* `DataDeprecated`  needs to be replaced with `Data` (https://docs.shopware.com/en/shopware-platform-dev-en/developer-guide/administration/fetching-and-handling-data?category=shopware-platform-dev-en/developer-guide/administration)
* Rename folder in `platform/src/Administration/Resources/app/administration/src/core` from `data-new`
to `data`. You need to rewrite the imports.
* Removed deprecated data handling and all its usages. See in the changelog if you
extend or override them. If yes, then you need to rewrite your code to support the 
actual data handling.

Storefront
--------------

Refactorings
------------
