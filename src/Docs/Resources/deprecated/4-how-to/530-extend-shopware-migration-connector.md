[titleEn]: <>(Extending the Migration Connector)
[metaDescriptionEn]: <>(This HowTo will give an example on extending the Migration Connector to migrate plugin data via API.)
[hash]: <>(article:how_to_extend_migration_connector)

## Overview

In this HowTo you will see an example on how you can extend the [Migration Connector](https://github.com/shopware/SwagMigrationConnector) 
plugin to migrate the Shopware 5 [SwagAdvDevBundle](https://github.com/shopwareLabs/SwagAdvDevBundle) to the Shopware 6 
[SwagBundleExample](./../1-getting-started/35-indepth-guide-bundle/010-introduction.md) plugin via API.

## Setup

It is required that you already have a basic Shopware 5 plugin running and to have installed the
[SwagAdvDevBundle](https://github.com/shopwareLabs/SwagAdvDevBundle), the [Migration Connector](https://github.com/shopware/SwagMigrationConnector) 
plugin in Shopware 5 and the [SwagBundleExample](./../1-getting-started/35-indepth-guide-bundle/010-introduction.md), 
[Migration Assistant](https://github.com/shopware/SwagMigrationAssistant) and 
[SwagMigrationBundleExample](./520-extend-shopware-migration-profile.md) plugin in Shopware 6. If you want to know, how all
plugins working together, please have a look on the [Extending a Shopware migration profile](./520-extend-shopware-migration-profile.md) HowTo.

With this setup you have the bundle plugin in Shopware 5 and also the bundle plugin in Shopware 6. So you  
can migrate your Shopware 5 shop to Shopware 6 via local and API gateway, but your bundle data only via local gateway.

## Creating bundle repository

To fetch your data via the Shopware 5 API you have to create a bundle repository first:

```php
<?php

namespace SwagMigrationBundleApiExample\Repository;

use Doctrine\DBAL\Connection;
use SwagMigrationConnector\Repository\AbstractRepository;

class BundleRepository extends AbstractRepository
{
    /**
     * Fetch bundles using offset and limit
     *
     * @param int $offset
     * @param int $limit
     *
     * @return array
     */
    public function fetch($offset = 0, $limit = 250)
    {
        $ids = $this->fetchIdentifiers('s_bundles', $offset, $limit);

        $query = $this->connection->createQueryBuilder();

        $query->from('s_bundles', 'bundles');
        $this->addTableSelection($query, 's_bundles', 'bundles');

        $query->where('bundles.id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $query->addOrderBy('bundles.id');

        return $query->execute()->fetchAll();
    }

    /**
     * Fetch all bundle products by bundle ids
     *
     * @param array $ids
     *
     * @return array
     */
    public function fetchBundleProducts(array $ids)
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('s_bundle_products', 'bundleProducts');
        $this->addTableSelection($query, 's_bundle_products', 'bundleProducts');

        $query->where('bundleProducts.bundle_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_COLUMN);
    }
}
```
The repository has to inherit from the `AbstractRepository` of the Migration Connector. This provides you with helper functions 
like `addTableSelection`, which sets a prefix to all table columns and add these to the query builder.

You have to register the repository in your`service.xml` with the parent property like this:

```xml
<service id="swag_migration_bundle_api_example.bundle_repository"
         class="SwagMigrationBundleApiExample\Repository\BundleRepository"
         parent="SwagMigrationConnector\Repository\AbstractRepository"
         />
```
## Creating bundle service

In the next step you create a new `BundleService`, which uses your new `BundleRepository` to fetch all bundles and products
to map them to one result array:

```php
<?php
/**
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationBundleApiExample\Service;

use SwagMigrationBundleApiExample\Repository\BundleRepository;
use SwagMigrationConnector\Repository\ApiRepositoryInterface;
use SwagMigrationConnector\Service\AbstractApiService;

class BundleService extends AbstractApiService
{
    /**
     * @var BundleRepository
     */
    private $bundleRepository;

    /**
     * @param ApiRepositoryInterface $bundleRepository
     */
    public function __construct(ApiRepositoryInterface $bundleRepository)
    {
        $this->bundleRepository = $bundleRepository;
    }

    /**
     * @param int $offset
     * @param int $limit
     *
     * @return array
     */
    public function getBundles($offset = 0, $limit = 250)
    {
        $bundles = $this->bundleRepository->fetch($offset, $limit);
        $ids = array_column($bundles, 'bundles.id');
        $bundleProducts = $this->bundleRepository->fetchBundleProducts($ids);
        
        // Strip the table prefix 'bundles' out of the bundles array
        $bundles = $this->mapData($bundles, [], ['bundles']);

        foreach ($bundles as &$bundle) {
            if (isset($bundleProducts[$bundle['id']])) {
                $bundle['products'] = $bundleProducts[$bundle['id']];
            }
        }

        return $this->cleanupResultSet($bundles);
    }
}
``` 

You have to register the `BundleService` in your `service.xml`:

```xml
<service class="SwagMigrationBundleApiExample\Service\BundleService" id="swag_migration_bundle_api_example.bundle_service">
    <argument type="service" id="swag_migration_bundle_api_example.bundle_repository"/>
</service>
```

## Create a new API controller

At last you have to create a new API controller, which uses the `BundleService` to get your bundle data:

```php
<?php
/**
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use SwagMigrationBundleApiExample\Service\BundleService;
use SwagMigrationConnector\Service\ControllerReturnStruct;

class Shopware_Controllers_Api_SwagMigrationBundles extends Shopware_Controllers_Api_Rest
{
    public function indexAction()
    {
        $offset = (int) $this->Request()->getParam('offset', 0);
        $limit = (int) $this->Request()->getParam('limit', 250);

        /** @var BundleService $bundleService */
        $bundleService = $this->container->get('swag_migration_bundle_api_example.bundle_service');

        $bundles = $bundleService->getBundles($offset, $limit);
        $response = new ControllerReturnStruct($bundles, empty($bundles));

        $this->view->assign($response->jsonSerialize());
    }
}
```

Because of the `BundleDataSet` of the [SwagMigrationBundleExample](./520-extend-shopware-migration-profile.md) plugin, 
you don't have to extend any Shopware 6 code. The return value of the `getApiRoute` method defines, which Shopware 5 API
route will be called:

```php
<?php declare(strict_types=1);

namespace SwagMigrationBundleExample\Profile\Shopware\DataSelection\DataSet;

use SwagMigrationAssistant\Migration\DataSelection\DataSet\CountingInformationStruct;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\CountingQueryStruct;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\ShopwareDataSet;
use SwagMigrationAssistant\Profile\Shopware\ShopwareProfileInterface;

class BundleDataSet extends ShopwareDataSet
{
    public static function getEntity(): string
    {
        return 'swag_bundle';
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof ShopwareProfileInterface;
    }

    public function getCountingInformation(): ?CountingInformationStruct
    {
        $information = new CountingInformationStruct(self::getEntity());
        $information->addQueryStruct(new CountingQueryStruct('s_bundles'));

        return $information;
    }

    public function getApiRoute(): string
    {
        return 'SwagMigrationBundles'; // This defines which API route should called
    }

    public function getExtraQueryParameters(): array
    {
        return [];
    }
}
```

And that's it, you're done and have already implemented your first plugin migration via API.

## Source

There's a GitHub repository available, containing a full example source.
Check it out [here](https://github.com/shopware/swag-docs-extending-shopware-migration-connector).
