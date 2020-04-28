[titleEn]: <>(Gateway and reader)
[hash]: <>(article:migration_reader)

Users will have to specify a gateway for the connection. The gateway defines the way of communicating with the source system.
Behind the user interface we use `Reader` objects to read the data from the source system.
For the `shopware55` profile we have the `api` gateway, which communicates via http/s with the source system,
and the `local` gateway, which communicates directly with the source system's database. Thus both systems must be on the 
same server for successfully using the `local` gateway.

## Gateway
The gateway defines how to communicate from Shopware 6 with your source system like Shopware 5. Every profile
needs to have at least one gateway. Gateways need to be defined in the corresponding service xml using the `shopware.migration.gateway` tag:
```xml
<!-- Shopware Profile Gateways -->
<service id="SwagMigrationAssistant\Profile\Shopware\Gateway\Local\ShopwareLocalGateway">
    <argument type="service" id="SwagMigrationAssistant\Profile\Shopware\Gateway\Local\ReaderRegistry" />
    <argument type="service" id="SwagMigrationAssistant\Profile\Shopware\Gateway\Local\Reader\LocalEnvironmentReader" />
    <argument type="service" id="SwagMigrationAssistant\Profile\Shopware\Gateway\Local\Reader\LocalTableReader" />
    <argument type="service" id="SwagMigrationAssistant\Profile\Shopware\Gateway\Local\Reader\LocalTableCountReader" />
    <argument type="service" id="SwagMigrationAssistant\Profile\Shopware\Gateway\Connection\ConnectionFactory" />
    <argument type="service" id="currency.repository"/>
    <tag name="shopware.migration.gateway" />
</service>

<service id="SwagMigrationAssistant\Profile\Shopware\Gateway\Api\ShopwareApiGateway">
    <argument type="service" id="SwagMigrationAssistant\Profile\Shopware\Gateway\Api\Reader\ApiReader" />
    <argument type="service" id="SwagMigrationAssistant\Profile\Shopware\Gateway\Api\Reader\ApiEnvironmentReader" />
    <argument type="service" id="SwagMigrationAssistant\Profile\Shopware\Gateway\Api\Reader\ApiTableReader" />
    <argument type="service" id="SwagMigrationAssistant\Profile\Shopware\Gateway\Api\Reader\ApiTableCountReader" />
    <argument type="service" id="currency.repository"/>
    <tag name="shopware.migration.gateway" />
</service>
```
If you want to use the `ShopwareApiGateway`, you will have to download the corresponding Shopware 5 plugin
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
    public function getGateways(MigrationContextInterface $migrationContext): array
    {
        $gateways = [];
        foreach ($this->gateways as $gateway) {
            if ($gateway->supports($migrationContext)) {
                $gateways[] = $gateway;
            }
        }

        return $gateways;
    }

    /**
     * @throws GatewayNotFoundException
     */
    public function getGateway(MigrationContextInterface $migrationContext): GatewayInterface
    {
        $profileName = $migrationContext->getConnection()->getProfileName();
        $gatewayName = $migrationContext->getConnection()->getGatewayName();

        foreach ($this->gateways as $gateway) {
            if ($gateway->supports($migrationContext) && $gateway->getName() === $gatewayName) {
                return $gateway;
            }
        }

        throw new GatewayNotFoundException($profileName . '-' . $gatewayName);
    }
}
```

The gateway class has to implement the `GatewayInterface` to support all required methods. As you can see below,
the gateway uses the right readers which internally open a connection to the source system to receive
the entity data:
```php
<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Profile\Shopware\Gateway\Local;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Currency\CurrencyEntity;
use SwagMigrationAssistant\Migration\EnvironmentInformation;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Profile\ReaderInterface;
use SwagMigrationAssistant\Migration\RequestStatusStruct;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\ShopwareDataSet;
use SwagMigrationAssistant\Profile\Shopware\Exception\DatabaseConnectionException;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Connection\ConnectionFactoryInterface;
use SwagMigrationAssistant\Profile\Shopware\Gateway\ShopwareGatewayInterface;
use SwagMigrationAssistant\Profile\Shopware\Gateway\TableCountReaderInterface;
use SwagMigrationAssistant\Profile\Shopware\Gateway\TableReaderInterface;
use SwagMigrationAssistant\Profile\Shopware\ShopwareProfileInterface;

class ShopwareLocalGateway implements ShopwareGatewayInterface
{
    public const GATEWAY_NAME = 'local';

    /**
     * @var ReaderRegistry
     */
    private $readerRegistry;

    /**
     * @var ReaderInterface
     */
    private $localEnvironmentReader;

    /**
     * @var TableReaderInterface
     */
    private $localTableReader;

    /**
     * @var TableCountReaderInterface
     */
    private $localTableCountReader;

    /**
     * @var ConnectionFactoryInterface
     */
    private $connectionFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    public function __construct(
        ReaderRegistry $readerRegistry,
        ReaderInterface $localEnvironmentReader,
        TableReaderInterface $localTableReader,
        TableCountReaderInterface $localTableCountReader,
        ConnectionFactoryInterface $connectionFactory,
        EntityRepositoryInterface $currencyRepository
    ) {
        $this->readerRegistry = $readerRegistry;
        $this->localEnvironmentReader = $localEnvironmentReader;
        $this->localTableReader = $localTableReader;
        $this->localTableCountReader = $localTableCountReader;
        $this->connectionFactory = $connectionFactory;
        $this->currencyRepository = $currencyRepository;
    }

    public function getName(): string
    {
        return self::GATEWAY_NAME;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof ShopwareProfileInterface;
    }

    public function read(MigrationContextInterface $migrationContext): array
    {
        /** @var ShopwareDataSet $dataSet */
        $dataSet = $migrationContext->getDataSet();

        $reader = $this->readerRegistry->getReader($migrationContext);

        return $reader->read($migrationContext, $dataSet->getExtraQueryParameters());
    }

    public function readEnvironmentInformation(MigrationContextInterface $migrationContext, Context $context): EnvironmentInformation
    {
        $connection = $this->connectionFactory->createDatabaseConnection($migrationContext);
        $profile = $migrationContext->getProfile();

        try {
            $connection->connect();
        } catch (\Exception $e) {
            $error = new DatabaseConnectionException();

            return new EnvironmentInformation(
                $profile->getSourceSystemName(),
                $profile->getVersion(),
                '-',
                [],
                [],
                new RequestStatusStruct($error->getErrorCode(), $error->getMessage())
            );
        }
        $connection->close();
        $environmentData = $this->localEnvironmentReader->read($migrationContext);

        /** @var CurrencyEntity $targetSystemCurrency */
        $targetSystemCurrency = $this->currencyRepository->search(new Criteria([Defaults::CURRENCY]), $context)->get(Defaults::CURRENCY);
        if (!isset($environmentData['defaultCurrency'])) {
            $environmentData['defaultCurrency'] = $targetSystemCurrency->getIsoCode();
        }

        $totals = $this->readTotals($migrationContext, $context);

        return new EnvironmentInformation(
            $profile->getSourceSystemName(),
            $profile->getVersion(),
            $environmentData['host'],
            $totals,
            $environmentData['additionalData'],
            new RequestStatusStruct(),
            false,
            [],
            $targetSystemCurrency->getIsoCode(),
            $environmentData['defaultCurrency']
        );
    }

    public function readTotals(MigrationContextInterface $migrationContext, Context $context): array
    {
        return $this->localTableCountReader->readTotals($migrationContext, $context);
    }

    public function readTable(MigrationContextInterface $migrationContext, string $tableName, array $filter = []): array
    {
        return $this->localTableReader->read($migrationContext, $tableName, $filter);
    }
}
```

Another task of the gateway is to fetch the environment information of the source system. The gateway
creates a connection to the source system and instantiates the environment reader to get the data from the source system.

Also the Counting Information from the [DataSets](./030-dataSelection-and-dataSet.md) will be fetched by the gateway.

## Reader
In case of the local gateway, each entity has a local reader.
These local readers fetch the data of the source system.
To prepare this data, the structure can be modified and associated data can be fetched:
```php
<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Profile\Shopware\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\ShopwareProfileInterface;

class LocalMediaReader extends LocalAbstractReader implements LocalReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof ShopwareProfileInterface
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::MEDIA;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);
        $fetchedMedia = $this->fetchData($migrationContext);

        $media = $this->mapData(
            $fetchedMedia, [], ['asset']
        );

        $resultSet = $this->prepareMedia($media);

        return $this->cleanupResultSet($resultSet);
    }

    private function fetchData(MigrationContextInterface $migrationContext): array
    {
        $ids = $this->fetchIdentifiers('s_media', $migrationContext->getOffset(), $migrationContext->getLimit());
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
        // represents the main language of the migrated shop
        $locale = $this->getDefaultShopLocale();

        foreach ($media as &$mediaData) {
            $mediaData['_locale'] = str_replace('_', '-', $locale);
        }
        unset($mediaData);

        return $media;
    }
}
```
