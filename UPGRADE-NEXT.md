# CHANGELOG for Shopware Next

This changelog references changes done in Shopware Next patch versions.

## Additions

* Added interface `Shopware\Components\Filesystem\FilesystemFactoryInterface` for filesystem creation
* Added interface `Shopware\Components\Filesystem\Adapter\AdapterFactoryInterface` for filesystem adapter creation
* Added additional filesystem adapter implementations for services:
	* Amazon Web Services
	* Microsoft Azure
	* Google Cloud Platform
* Added container tag `shopware.filesystem.factory` for additional filesystem adapter factories
* Added service `shopware.filesystem.public` and `shopware.filesystem.private` for file handling
* Added automatic prefixed filesystem service registration for plugins
	* `plugin_name.filesystem.public`
	* `plugin_name.filesystem.private`
* Added method `Shopware\Bundle\MediaBundle\Strategy\StrategyInterface::getName()` to identify the strategy
* Added interface `Shopware\Bundle\MediaBundle\MediaMigrationInterface`
* Added method `Shopware\Bundle\MediaBundle\MediaServiceInterface::getFilesystem()`
* Added config parameter `shopware.cdn.url` which replaces the `mediaUrl` that was previously defined in `shopware.cdn.adapters`
* Added service `shopware_media.strategy_factory`
* Added container tag `shopware_media.strategy` for strategy registration
* Added abstract class `Shopware\Components\Filesystem\AbstractFilesystem`
* Added service `shopware_media.filesystem` which is build on top of `shopware.filesystem.public`
* Added `\Shopware\Category\Struct\Category::__construct` which requires id, parentId, path and name
* Added `\Shopware\Bundle\StoreFrontBundle\AdvancedMenu\AdvancedMenuService` to get advanced menu
* Added block `frontend_advanced_menu_outer` in `themes/Frontend/Bare/frontend/index/index.tpl` for advanced menu
* Added `\Shopware\Controllers\Widgets\AdvancedMenu` controller
* Added `\Shopware\Bundle\StoreFrontBundle\Common\KeyCollection` - used for associated arrays 
* Added `\Shopware\Framework\Struct\Collection` - used for none associated arrays
* Added `\Shopware\Category\Struct\CategoryCollection` - Typed key collection for category structs

## Changes

* Changed `BackendSession` service name to `backend_session`
* Changed `Shopware/Plugins/Default/Frontend/AdvancedMenu`
	* Implementation moved to `\Shopware\Bundle\StoreFrontBundle\AdvancedMenu\AdvancedMenuService`
	* Moved `Shopware/Plugins/Default/Frontend/AdvancedMenu/Snippets/frontend/plugins/advanced_menu/advanced_menu.ini` snippets to `snippets/frontend/advancedmenu/index.ini`
	* Moved `Shopware/Plugins/Default/Frontend/AdvancedMenu/Views/frontend/advanced_menu/index.tpl` to `themes/Frontend/Bare/widgets/advanced_menu/index.tpl` with renamed blocks:
		- `frontend_plugins_advanced_menu_list` to `frontend_advanced_menu_list`
		- `frontend_plugins_advanced_menu_list_item` to `frontend_advanced_menu_list_item`
		- `frontend_plugins_advanced_menu` to `frontend_advanced_menu`
		- `frontend_plugins_advanced_menu_main_container` to `frontend_advanced_menu_main_container`
		- `frontend_plugins_advanced_menu_button_category` to `frontend_advanced_menu_button_category`
		- `frontend_plugins_advanced_menu_button_close` to `frontend_advanced_menu_button_close`
		- `frontend_plugins_advanced_menu_sub_categories` to `frontend_advanced_menu_sub_categories`
		- `frontend_plugins_advanced_menu_teaser` to `frontend_advanced_menu_teaser`
	* Moved `Shopware/Plugins/Default/Frontend/AdvancedMenu/Views/frontend/_public/src/js/jquery.advanced-menu.js` to `themes/Frontend/Responsive/frontend/_public/src/js/jquery.advanced-menu.js`
	* Moved `Shopware/Plugins/Default/Frontend/AdvancedMenu/Views/frontend/_public/src/less/advanced-menu.less` to `themes/Frontend/Responsive/frontend/_public/src/less/_components/advanced-menu.less`
	* Changed `category.sub` variable to `category.children` in advanced menu template.
	
* Changed class `Shopware\Bundle\MediaBundle\Commands\ImageMigrateCommand` to `Shopware\Bundle\MediaBundle\Commands\MediaMigrateCommand`
* Changed command `sw:media:migrate` to switch between strategies instead of moving files to different filesystems
* Changed 3rd constructor parameter in `shopware_media.garbage_collector` from `Shopware\Bundle\MediaBundle\MediaServiceInterface` to `Shopware\Bundle\MediaBundle\Strategy\StrategyInterface`
* Changed 3rd constructor parameter in `shopware_media.garbage_collector_factory` from `Shopware\Bundle\MediaBundle\MediaServiceInterface` to `Shopware\Bundle\MediaBundle\Strategy\StrategyInterface`
* Changed constructor of `shopware_media.strategy_factory` to require a collection of `Shopware\Bundle\MediaBundle\Strategy\StrategyInterface`
* Changed default path of `media` to `web/media`
* Changed context requirement to new `\Shopware\Context\ShopContext` in all `Shopware\Bundle\StoreFrontBundle` gateways
* Changed `Shopware\Bundle\StoreFrontBundle\Tax\TaxGateway::getRules` parameters. Removed area, country, state and use new `ShippingLocation` 
* Changed service structure in StoreFrontBundle. Services, structs and gateways are now stored in domain namespaces. 
    * Example: `Shopware\Bundle\StoreFrontBundle\Service\Core\ManufacturerService` moved to `Shopware\Bundle\StoreFrontBundle\Manufacturer\ManufacturerService`
    * Service names changed as follow: `shopware_storefront.manufacturer_service` to `storefront.manufacturer.service`

## Removals

* Removed support for separate SSL host and SSL path. Also the `Use SSL` and `Always SSL` options were merged.
    * Removed database fields
        - `s_core_shops.secure_host`
        - `s_core_shops.secure_base_path`
        - `s_core_shops.always_secure`

    * Removed methods
        - `\Shopware\Bundle\StoreFrontBundle\Shop\Shop::setSecureHost`
        - `\Shopware\Bundle\StoreFrontBundle\Shop\Shop::getSecureHost`
        - `\Shopware\Bundle\StoreFrontBundle\Shop\Shop::setSecurePath`
        - `\Shopware\Bundle\StoreFrontBundle\Shop\Shop::getSecurePath`
        - `\Shopware\Components\Routing\Context::getSecureHost`
        - `\Shopware\Components\Routing\Context::setSecureHost`
        - `\Shopware\Components\Routing\Context::getSecureBaseUrl`
        - `\Shopware\Components\Routing\Context::setSecureBaseUrl`
        - `\Shopware\Components\Routing\Context::isAlwaysSecure`
        - `\Shopware\Components\Routing\Context::setAlwaysSecure`
        - `\Shopware\Models\Shop\Shop::getSecureHost`
        - `\Shopware\Models\Shop\Shop::setSecureHost`
        - `\Shopware\Models\Shop\Shop::getSecureBasePath`
        - `\Shopware\Models\Shop\Shop::setSecureBasePath`
        - `\Shopware\Models\Shop\Shop::getSecureBaseUrl`
        - `\Shopware\Models\Shop\Shop::setSecureBaseUrl`
        - `\Shopware\Models\Shop\Shop::getAlwaysSecure`
        - `\Shopware\Models\Shop\Shop::setAlwaysSecure`
        - `\Shopware_Controllers_Widgets_Listing::tagCloudAction`
        - `\sMarketing::sBuildTagCloud`

    * Removed plugins
        - `TagCloud`

    * Removed blocks
        - `frontend_listing_index_tagcloud` in file `themes/Frontend/Bare/frontend/listing/index.tpl`
        - `frontend_home_index_tagcloud` in file `themes/Frontend/Bare/frontend/home/index.tpl`

    * Deprecated `forceSecure` and `sUseSSL` smarty flags

* Removed Shopware_Plugins_Backend_Auth_Bootstrap
    * Implementation moved to \Shopware\Components\Auth\BackendAuthSubscriber
    
* Removed `Shopware/Plugins/Default/Frontend/AdvancedMenu/Views/frontend/index/index.tpl` with block `frontend_plugins_advanced_menu_outer`
* Removed `s_core_engine_elements` and `Shopware\Models\Article\Element`
* Removed config parameter `shopware.cdn.adapters`
* Removed config parameter `shopware.cdn.liveMigration`
* Removed config parameter `shopware.cdn.backend`
* Removed compiler pass `MediaAdapterCompilerPass` including the container tag `shopware_media.adapter`
* Removed interface `Shopware\Bundle\MediaBundle\Adapters\AdapterFactoryInterface`
* Removed class `Shopware\Bundle\MediaBundle\Adapters\FtpAdapterFactory.php` and service `shopware_media.adapter.ftp`
* Removed class `Shopware\Bundle\MediaBundle\Adapters\LocalAdapterFactory.php` and service `shopware_media.adapter.local`
* Removed parameters `$fromFilesystem`, `$toFilesystem` and `$output` from `Shopware\Bundle\MediaBundle\MediaMigration::migrate()`, they are now constructor parameters
* Removed class `Shopware\Bundle\MediaBundle\MediaServiceFactory` since the adapter configuration has been removed from the MediaBundle
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::read()`, use `getFilesystem()->read()`
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::readStream()`, use `getFilesystem()->readStream()`
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::write()`, use `getFilesystem()->write()`
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::writeStream()`, use `getFilesystem()->writeStream()`
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::listFiles()`, use `getFilesystem()->listContents()`
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::has()`, use `getFilesystem()->has()`
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::delete()`, use `getFilesystem()->delete()`
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::rename()`, use `getFilesystem()->rename()`
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::normalize()`, use `normalize()` in `shopware_media.strategy` instead
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::encode()`, use `encode()` in `shopware_media.strategy` instead
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::isEncoded()`, use `isEncoded()` in `shopware_media.strategy` instead
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::getAdapterType()`
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::createDir()`, use `getFilesystem()->createDir()`
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::migrateFile()`
* Removed method `Shopware\Bundle\MediaBundle\MediaServiceInterface::getAdapter()`, use `getFilesystem()` instead
* Removed config option `esdDownloadStrategy` without replacement
* Removed method `Shopware_Plugins_Frontend_Statistics_Bootstrap::checkIsBot`, use `Shopware\Components\Statistics\BotDetectorInterface` instead
* Removed method `Shopware_Plugins_Frontend_Statistics_Bootstrap::refreshBasket`, use `Shopware\Components\Statistics\Tracer\CartTracer` instead
* Removed method `Shopware_Plugins_Frontend_Statistics_Bootstrap::refreshLog`, use `Shopware\Components\Statistics\Tracer\VisitorTracer` instead
* Removed method `Shopware_Plugins_Frontend_Statistics_Bootstrap::refreshReferer`, use `\Shopware\Components\Statistics\Tracer\RefererTracer` instead
* Removed method `Shopware\Models\Tracking\Repository::getArticleImpressionQuery`
* Removed method `Shopware\Models\Tracking\Repository::getArticleImpressionQueryBuilder`
* Removed method `Shopware_Plugins_Frontend_Statistics_Bootstrap::refreshArticleImpression`, use `Shopware\Components\Statistics\Tracer\ProductImpressionTracer` instead
* Removed method `Shopware_Plugins_Frontend_Statistics_Bootstrap::refreshCurrentUsers`, use `Shopware\Components\Statistics\Tracer\CurrentCustomersTracer` instead
* Removed method `Shopware_Plugins_Frontend_Statistics_Bootstrap::refreshPartner`, use `Shopware\Components\Statistics\Tracer\PartnerTracer` instead
* Removed class `Shopware_Plugins_Frontend_Statistics_Bootstrap`, use ``\Shopware\Components\Statistics\StatisticRegistry` instead
* Removed unnecessary `get` function in store front bundle services and gateways, use `getList` instead. Following classes and function are affected:
    * `\Shopware\Bundle\StoreFrontBundle\Category\CategoryServiceInterface::get`
    * `\Shopware\Bundle\StoreFrontBundle\Category\CategoryGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\Price\CheapestPriceServiceInterface::get`
    * `\Shopware\Bundle\StoreFrontBundle\Prirce\CheapestPriceGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\Country\CountryGateway::getArea`
    * `\Shopware\Bundle\StoreFrontBundle\Country\CountryGateway::getCountry`
    * `\Shopware\Bundle\StoreFrontBundle\Country\CountryGateway::getState`
    * `\Shopware\Bundle\StoreFrontBundle\CustomerGroup\CustomerGroupGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\ProductDownload\ProductDownloadGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\ProductDownload\ProductDownloadServiceInterface::get`
    * `\Shopware\Bundle\StoreFrontBundle\ProductDownload\ProductDownloadGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\Prirce\GraduatedPricesGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\Price\GraduatedPricesServiceInterface::get`
    * `\Shopware\Bundle\StoreFrontBundle\ProductLink\ProductLinkGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\ProductLink\ProductLinkServiceInterface::get`
    * `\Shopware\Bundle\StoreFrontBundle\Product\ListProductGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\Manufacturer\ManufacturerGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\Media\MediaGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\Media\MediaService::get`
    * `\Shopware\Bundle\StoreFrontBundle\Configurator\ProductConfigurationGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\Configurator\ConfiguratorServiceInterface::getProductConfiguration`
    * `\Shopware\Bundle\StoreFrontBundle\Media\ProductMediaGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\Media\ProductMediaGateway::getCover`
    * `\Shopware\Bundle\StoreFrontBundle\Property\ProductPropertyGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\RelatedProduct\RelatedProductsGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\ProductStream\RelatedProductStreamsGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\Shop\ShopGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\SimilarProduct\SimilarProductsGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\Media\VariantMediaGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\Media\VariantMediaGateway::getCover`
    * `\Shopware\Bundle\StoreFrontBundle\Vote\VoteAverageGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\Vote\VoteGateway::get`
    * `\Shopware\Bundle\StoreFrontBundle\AdditionalText\AdditionalTextServiceInterface::buildAdditionalText`
    * `\Shopware\Bundle\StoreFrontBundle\Product\BaseProductFactoryService::createBaseProduct`
    * `\Shopware\Bundle\StoreFrontBundle\Product\ListProductServiceInterface::get`
    * `\Shopware\Bundle\StoreFrontBundle\Manufacturer\ManufacturerServiceInterface::get`
    * `\Shopware\Bundle\StoreFrontBundle\Media\MediaServiceInterface::get`
    * `\Shopware\Bundle\StoreFrontBundle\Media\MediaServiceInterface::getCover`
    * `\Shopware\Bundle\StoreFrontBundle\Media\MediaServiceInterface::getProductMedia`
    * `\Shopware\Bundle\StoreFrontBundle\Product\ProductServiceInterface::get`
    * `\Shopware\Bundle\StoreFrontBundle\Property\PropertyServiceInterface::get`
    * `\Shopware\Bundle\StoreFrontBundle\RelatedProduct\RelatedProductsServiceInterface::get`
    * `\Shopware\Bundle\StoreFrontBundle\ProductStream\RelatedProductStreamsServiceInterface::get`
    * `\Shopware\Bundle\StoreFrontBundle\SimilarProduct\SimilarProductsServiceInterface::get`
    * `\Shopware\Bundle\StoreFrontBundle\Vote\VoteServiceInterface::get`
    * `\Shopware\Bundle\StoreFrontBundle\Vote\VoteServiceInterface::getAverage`

* Removed `\Shopware\Bundle\StoreFrontBundle\Struct\LocationContext`
* Removed `\Shopware\Bundle\StoreFrontBundle\SimilarProduct\SimilarProductsGateway::getListByCategory`
* Removed `\Shopware\Bundle\StoreFrontBundle\SimilarProduct\SimilarProductsGateway::getByCategory`
* Removed `\Shopware\Bundle\StoreFrontBundle\Context\ContextService::getContext`
* Removed `\Shopware\Bundle\StoreFrontBundle\Context\ContextService::getProductContext`
* Removed `\Shopware\Bundle\StoreFrontBundle\Context\ContextService::getLocationContext`
* Removed `\Shopware\Bundle\StoreFrontBundle\Context\ContextService::initializeContext`
* Removed `\Shopware\Bundle\StoreFrontBundle\Context\ContextService::initializeLocationContext`
* Removed `\Shopware\Bundle\StoreFrontBundle\Context\ContextService::initializeProductContext`
* Removed `\Shopware\Bundle\StoreFrontBundle\Context\ContextService::createProductContext`
* Removed `Shopware\Bundle\StoreFrontBundle\Context\ContextServiceInterface::createShopContext` use `Shopware\Bundle\StoreFrontBundle\Context\ContextFactoryInterface::create` instead
* Removed `Shopware\Bundle\StoreFrontBundle\Context\ShopContextInterface::getBaseUrl`
* Removed `Shopware\Bundle\StoreFrontBundle\Service\VariantCoverServiceInterface`, use `Shopware\Bundle\StoreFrontBundle\Media\MediaServiceInterface::getVariantCovers` instead

## Filesystem

There are two filesystems for private and public purposes. They are meant for shared files like media or invoices that need to be available on every application server.

In addition, every installed and activated plugin gets its own space within your public or private filesystem. So, plugin developer don't have to worry about existing files by other plugins.

* The `private` namespace should be used for files, which **are not** accessable by the webroot like invoices or temporary files.
* The `public` namespace should be used for files, which **are** accessable by the webroot like media files, assets, ...

### Usage

Creating files is really easy. Just point to the file you want to write to and specify the contents or stream.

```php
$filesystem = $this->container->get('shopware.filesystem.private');
$filesystem->write('path/to/file.pdf', $invoiceContents);
// or using streams
$filesystem->writeStream('path/to/file.pdf', $invoiceStream);
```

*Keep in mind, that you have to provide access to your files using a gateway controller.*

Imagine to provide a download for the created file above is simple too.

```php
$filesystem = $this->container->get('shopware.filesystem.private');
$filesystem->read('path/to/file.pdf', $invoiceContents);
// or using streams
$filesystem->readStream('path/to/file.pdf', $invoiceStream);
```

For a more detail overview of the filesystem api, please refer to [thephpleague/flysystem](https://github.com/thephpleague/flysystem).

### Prefixed plugin filesystems

Each installed and activate plugin gets its own prefixed filesystem. Imagine a plugin named `SwagBonus`, you can access the plugins filesystem using the following services:

```php
$filesystem = $this->container->get('swag_bonus.filesystem.public');
// or private filesystem
$filesystem = $this->container->get('swag_bonus.filesystem.private');
```

The file will be stored in the global Shopware filesystem prefixed with `pluginData/pluginName`, e.g. `pluginData/SwagBonus`.

```php
$global = $this->container->get('shopware.filesystem.private');
$plugin->write('path/fo/file.pdf', $contents);
// will be stored in `path/to/file.pdf`

$plugin = $this->container->get('swag_bonus.filesystem.private');
$plugin->write('path/fo/file.pdf', $contents);
// will be saved in `pluginData/SwagBonus/path/to/file.pdf`
```

### Using external services

You can choose where to store your files. By default, they will be stored on the application server where the script gets executed. There are 3 additional services supported out-of-the-box.

#### Amazon Web Services

To save your files on AWS S3, you have to modify your `config.php` and overwrite the filesystem you want to replace.

The following example will store all `public` files on AWS S3.

```php
'filesystem' => [
    'public' => [
        'type' => 'amazon-s3',
        'config' => [
            'bucket' => 'your-s3-bucket-name',
            'region' => 'your-bucket-region',
            'credentials' => [
                'key' => 'your-app-key',
                'secret' => 'your-app-secret',
            ],
        ],
    ],
],
```

#### Microsoft Azure

To save your files on Microsoft Azure, you have to modify your `config.php` and overwrite the filesystem you want to replace.

The following example will store all `public` files on Microsoft Azure.

```php
'filesystem' => [
    'public' => [
        'type' => 'microsoft-azure',
        'config' => [
            'container' => 'my-container-name',
            'apiKey' => 'my-api-key',
            'accountName' => 'my-account-name',
        ],
    ],
],
```



#### Google Cloud Platform

To save your files on Google Cloud Platform, you have to modify your `config.php` and overwrite the filesystem you want to replace.

The following example will store all `public` files on Google Cloud Platform.

```php
'filesystem' => [
    'public' => [
        'type' => 'google-storage',
        'config' => [
            'projectId' => 'your-project-id',
            'bucket' => 'your-bucket-name',
            'keyFilePath' => 'path/to/your/application_credentials.json',
        ],
    ],
],
```

## `\Shopware\Category\Struct\CategoryCollection`
The category collection provides different helper function to work with categories:

* `getIds` - returns all ids of contained categories
    ```
    $collection = new CategoryCollection([
        new Category(1, null, [], 'First level 01'),
        new Category(2, 1, [1], 'Second level 01'),
    ]);

    $this->assertSame([1,2], $collection->getIds());
    ```

* `getPaths` - returns all path variables of the contained categories
    ```
    $collection = new CategoryCollection([
        new Category(1, null, [1], 'First level 01'),
        new Category(2, 1, [1, 2], 'Second level 01'),
    ]);
    $this->assertSame(
        [ [1], [2, 1] ],
        $collection->getPaths()
    );
    ```

* `getIdsIncludingPaths` - returns all ids, including ids of the categories path
    ```
    $collection = new CategoryCollection([
        new Category(2, 1, [1], 'Second level 01'),
        new Category(5, 50, [50, 1], 'Third level 02'),
    ]);

    $this->assertSame(
        [1,2,5,50],
        $collection->getIdsIncludingPaths()
    );
    ```


* `getTree` - Allows to build a category tree, started with the provided parent
    ```
    $collection = new CategoryCollection([
        new Category(1, null, [], 'First level 01'),
        new Category(2, 1, [1], 'Second level 01'),
        new Category(3, 2, [2, 1], 'Third level 01'),
        new Category(4, 1, [1], 'Second level 02'),
        new Category(5, 4, [4, 1], 'Third level 02'),
    ]);

    $this->assertEquals(
        [
            new Category(1, null, [], 'First level 01', [
                new Category(2, 1, [1], 'Second level 01', [
                    new Category(3, 2, [2, 1], 'Third level 01'),
                ]),
                new Category(4, 1, [1], 'Second level 02', [
                    new Category(5, 4, [4, 1], 'Third level 02'),
                ]),
            ]),
        ],
        $collection->getTree(null)
    );
    ```
