[titleEn]: <>(Gateway and reader)

Users will have to specify a gateway for the connection. The gateway defines the way of communicating with the source system.
Behind the user interface we use `Reader` objects to read the data from the source system.
For the `shopware55` profile we have the `api` gateway, which communicates via http/s with the source system,
and the `local` gateway, which communicates directly with the source system's database. Thus both systems must be on the 
same server for successfully using the `local` gateway.

## Gateway
The gateway defines how to communicate from Shopware 6 with your source system like Shopware 5. Every profile
needs to have at least one gateway. Gateways need to be defined in the corresponding service xml using the `shopware.migration.gateway` tag:
```xml
<service id="SwagMigrationAssistant\Profile\Shopware55\Gateway\Local\Shopware55LocalGateway">
    <tag name="shopware.migration.gateway" />
</service>

<service id="SwagMigrationAssistant\Profile\Shopware55\Gateway\Api\Shopware55ApiGateway">
    <tag name="shopware.migration.gateway" />
</service>
```
If you want to use the `Shopware55ApiGateway`, you will have to download the corresponding Shopware 5 plugin
[Shopware Migration Connector](https://github.com/shopware/SwagMigrationConnector), first.

This tag is used by `GatwayRegistry`. This registry loads all tagged gateways and chooses a suitable gateway based on
the migration's context and a unique identifier, composed by a combination of profile and gateway name:
```php
<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Migration\Gateway;

use SwagMigrationAssistant\Exception\GatewayNotFoundException;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class GatewayRegistry implements GatewayRegistryInterface
{
    /**
     * @var GatewayInterface[]
     */
    private $gateways;

    public function __construct(iterable $gateways)
    {
        $this->gateways = $gateways;
    }

    /**
     * @throws GatewayNotFoundException
     */
    public function getGateway(MigrationContextInterface $migrationContext): GatewayInterface
    {
        $gatewayIdentifier = $migrationContext->getProfileName() . $migrationContext->getGatewayName();

        foreach ($this->gateways as $gateway) {
            if ($gateway->supports($gatewayIdentifier)) {
                return $gateway;
            }
        }

        throw new GatewayNotFoundException($gatewayIdentifier);
    }
}

```

The gateway class has to implement the `GatewayInterface` to support all required methods. As you can see below,
the gateway opens a connection to the source system and instantiates readers that depend on a given entity to receive
the entity data:

```php
<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Profile\Shopware55\Gateway\Local;

use SwagMigrationAssistant\Migration\EnvironmentInformation;
use SwagMigrationAssistant\Migration\Gateway\GatewayInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware55\DataSelection\DataSet\CategoryDataSet;
use SwagMigrationAssistant\Profile\Shopware55\DataSelection\DataSet\ProductDataSet;
use SwagMigrationAssistant\Profile\Shopware55\DataSelection\DataSet\Shopware55DataSet;
use SwagMigrationAssistant\Profile\Shopware55\Gateway\Local\Reader\Shopware55LocalCategoryReader;
use SwagMigrationAssistant\Profile\Shopware55\Gateway\Local\Reader\Shopware55LocalEnvironmentReader;
use SwagMigrationAssistant\Profile\Shopware55\Gateway\Local\Reader\Shopware55LocalProductReader;
use SwagMigrationAssistant\Profile\Shopware55\Gateway\Local\Reader\Shopware55LocalReaderNotFoundException;
use SwagMigrationAssistant\Profile\Shopware55\Shopware55Profile;

class Shopware55LocalGateway implements GatewayInterface
{
    public const GATEWAY_NAME = 'local';

    public function supports(string $gatewayIdentifier): bool
    {
        return $gatewayIdentifier === Shopware55Profile::PROFILE_NAME . self::GATEWAY_NAME;
    }

    public function read(MigrationContextInterface $migrationContext): array
    {
        $connection = $this->getConnection($migrationContext);
        /** @var Shopware55DataSet $dataSet */
        $dataSet = $migrationContext->getDataSet();

        switch ($dataSet::getEntity()) {
            case ProductDataSet::getEntity():
                $reader = new Shopware55LocalProductReader($connection, $migrationContext);

                return $reader->read();
            case CategoryDataSet::getEntity():
                $reader = new Shopware55LocalCategoryReader($connection, $migrationContext);

                return $reader->read();
                
            /* ... */
                
            default:
                throw new Shopware55LocalReaderNotFoundException($dataSet::getEntity());
        }
    }

    public function readEnvironmentInformation(MigrationContextInterface $migrationContext): EnvironmentInformation
    {
        $connection = $this->getConnection($migrationContext);
        $connection->connect();
        
        $reader = new Shopware55LocalEnvironmentReader($connection, $migrationContext);
        $environmentData = $reader->read();

        /* ... */

        return $environmentInformation;
    }

    /* ... */
}
```

Another task of the gateway is to fetch the environment information of the source system. Like in the read function the gateway
creates a connection to the source system and instantiates the environment reader to get the data from the source system.

## Reader
In case of the local gateway, each entity has a local reader.
These local readers fetch the data of the source system.
To prepare this data, the structure can be modified and associated data can be fetched:
```php
<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Profile\Shopware55\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;

class Shopware55LocalMediaReader extends Shopware55LocalAbstractReader
{
    public function read(): array
    {
        $fetchedMedia = $this->fetchData();

        $media = $this->mapData(
            $fetchedMedia, [], ['asset']
        );

        $resultSet = $this->prepareMedia($media);

        return $this->cleanupResultSet($resultSet);
    }

    private function fetchData(): array
    {
        $ids = $this->fetchIdentifiers('s_media', $this->migrationContext->getOffset(), $this->migrationContext->getLimit());
        $query = $this->connection->createQueryBuilder();

        $query->from('s_media', 'asset');
        $this->addTableSelection($query, 's_media', 'asset');

        $query->leftJoin('asset', 's_media_attributes', 'attributes', 'asset.id = attributes.mediaID');
        $this->addTableSelection($query, 's_media_attributes', 'attributes');

        $query->where('asset.id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $query->addOrderBy('asset.id');

        return $query->execute()->fetchAll();
    }

    private function prepareMedia(array $media): array
    {
        $locale = $this->getDefaultShopLocale();

        foreach ($media as &$mediaData) {
            $mediaData['_locale'] = str_replace('_', '-', $locale);
        }
        unset($mediaData);

        return $media;
    }
}
```