[titleEn]: <>(Creating a new migration profile)
[metaDescriptionEn]: <>(This HowTo will give an example on creating a new migration profile for the Migration Assistant.)
[hash]: <>(article:how_to_create_migration_profile)

## Overview

If you want to migrate your data from a different source system than Shopware, create a new migration profile for
the Migration Assistant. But if you want to convert your plugin data from a Shopware system to Shopware 6, please have a look
at this HowTo: "[extend a shopware migration profile](./520-extend-shopware-migration-profile.md).

## Setup

First of all, it is required that you already have installed the [Migration Assistant](https://github.com/shopware/SwagMigrationAssistant)
plugin in Shopware 6 and have created a demo source system database with a `product` table.
To create the table, use this SQL statement:
```sql
CREATE TABLE product
(
  id int PRIMARY KEY NOT NULL AUTO_INCREMENT,
  product_number varchar(255) NOT NULL,
  price float NOT NULL,
  stock int NOT NULL,
  product_name varchar(255) NOT NULL,
  tax float NOT NULL
);
```

This table should simulate simple a third party source system, which should be migrated in the following steps.

## Creating a profile

In the first step, you have to create a new profile for your source system:

```php
<?php declare(strict_types=1);

namespace SwagMigrationOwnProfileExample;

use SwagMigrationAssistant\Migration\Profile\ProfileInterface;

class OwnProfile implements ProfileInterface
{
    public const PROFILE_NAME = 'ownProfile';

    public const SOURCE_SYSTEM_NAME = 'MySourceSystem';

    public const SOURCE_SYSTEM_VERSION = '1.0';

    public function getName(): string
    {
        return self::PROFILE_NAME;
    }

    public function getSourceSystemName(): string
    {
        return self::SOURCE_SYSTEM_NAME;
    }

    public function getVersion(): string
    {
        return self::SOURCE_SYSTEM_VERSION;
    }
}
```

The profile itself does not contain any logic and is used to bundle the executing classes. To use this profile, you have to
register and tag it in the `service.xml` with `shopware.migration.profile`:

```xml
<service id="SwagMigrationOwnProfileExample\Profile\OwnProfile\OwnProfile">
    <tag name="shopware.migration.profile"/>
</service>
```

## Creating a gateway

Next, you have to create a new gateway, which supports your profile:

```php
<?php declare(strict_types=1);

namespace SwagMigrationOwnProfileExample\Profile\OwnProfile\Gateway;

use Shopware\Core\Framework\Context;
use SwagMigrationAssistant\Migration\EnvironmentInformation;
use SwagMigrationAssistant\Migration\Gateway\GatewayInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\RequestStatusStruct;
use SwagMigrationAssistant\Profile\Shopware\Exception\DatabaseConnectionException;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Connection\ConnectionFactoryInterface;
use SwagMigrationAssistant\Profile\Shopware\Gateway\TableCountReaderInterface;
use SwagMigrationOwnProfileExample\Profile\OwnProfile\OwnProfile;

class OwnLocaleGateway implements GatewayInterface
{
    public const GATEWAY_NAME = 'local';

    private $connectionFactory;

    /**
     * @var TableCountReaderInterface
     */
    private $localTableCountReader;

    public function __construct(
        ConnectionFactoryInterface $connectionFactory,
        TableCountReaderInterface $localTableCountReader
    ) {
        $this->connectionFactory = $connectionFactory;
        $this->localTableCountReader = $localTableCountReader;
    }

    public function getName(): string
    {
        return self::GATEWAY_NAME;
    }

    /**
     * Identifier for a gateway registry
     */
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OwnProfile;
    }

    /**
     * Reads the given entity type from via context from its connection and returns the data
     */
    public function read(MigrationContextInterface $migrationContext): array
    {
        // TODO: Implement read() method.
        return [];
    }

    public function readEnvironmentInformation(
        MigrationContextInterface $migrationContext,
        Context $context
    ): EnvironmentInformation {
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

        $totals = $this->readTotals($migrationContext, $context);

        return new EnvironmentInformation(
            $profile->getSourceSystemName(),
            $profile->getVersion(),
            'Example Host Name',
            $totals,
            [],
            new RequestStatusStruct(),
            false
        );
    }

    public function readTotals(MigrationContextInterface $migrationContext, Context $context): array
    {
        return $this->localTableCountReader->readTotals($migrationContext, $context);
    }
}
```

As you have seen above, the gateway uses the `ConnectionFactory` to test the connection to the source system. You can also implement
your own way to check this, but to use this factory is the simplest way for a gateway to connect a local database. Like the profile you
have to register the new gateway in the `service.xml` and tag it with `shopware.migration.gateway`:

```xml
<service id="SwagMigrationOwnProfileExample\Profile\OwnProfile\Gateway\OwnLocaleGateway">
    <argument type="service" id="SwagMigrationAssistant\Profile\Shopware\Gateway\Connection\ConnectionFactory"/>
    <argument type="service" id="SwagMigrationAssistant\Profile\Shopware\Gateway\Local\Reader\LocalTableCountReader"/>
    <tag name="shopware.migration.gateway"/>
</service>
```

## Creating a credentials page

If you would like to try your current progress in the administration, you could select the profile and gateway in the migration wizard already.
If you try to go to the next page, there will be an error message, because no credentials page was found. To create
a new credentials page, you have to add an `index.js` for your new component into `Resources/app/administration/src/own-profile/profile`:

```js
import { Component } from 'src/core/shopware';
import template from './swag-migration-profile-ownProfile-locale-credential-form.html.twig';

Component.register('swag-migration-profile-ownProfile-local-credential-form', {
    template,

    props: {
        credentials: {
            type: Object,
            default() {
                return {};
            }
        }
    },

    data() {
        return {
            inputCredentials: {
                dbHost: '',
                dbPort: '3306',
                dbUser: '',
                dbPassword: '',
                dbName: ''
            }
        };
    },

    watch: {
        credentials: {
            immediate: true,
            handler(newCredentials) {
                if (newCredentials === null) {
                    this.emitCredentials(this.inputCredentials);
                    return;
                }

                this.inputCredentials = newCredentials;
                this.emitOnChildRouteReadyChanged(
                    this.areCredentialsValid(this.inputCredentials)
                );
            }
        },

        inputCredentials: {
            deep: true,
            handler(newInputCredentials) {
                this.emitCredentials(newInputCredentials);
            }
        }
    },

    methods: {
        areCredentialsValid(newInputCredentials) {
            return (newInputCredentials.dbHost !== '' &&
                newInputCredentials.dbPort !== '' &&
                newInputCredentials.dbName !== '' &&
                newInputCredentials.dbUser !== '' &&
                newInputCredentials.dbPassword !== ''
            );
        },

        emitOnChildRouteReadyChanged(isReady) {
            this.$emit('onChildRouteReadyChanged', isReady);
        },

        emitCredentials(newInputCredentials) {
            this.$emit('onCredentialsChanged', newInputCredentials);
            this.emitOnChildRouteReadyChanged(
                this.areCredentialsValid(newInputCredentials)
            );
        },

        onKeyPressEnter() {
            this.$emit('onTriggerPrimaryClick');
        }
    }
});
```
As you can see above, currently the template does not exists and you have to create
this file: `swag-migration-profile-ownProfile-locale-credential-form.html.twig`

```twig
{% block own_profile_page_credentials %}
    <div class="swag-migration-wizard swag-migration-wizard-page-credentials"
         @keypress.enter="onKeyPressEnter">
        {% block own_profile_page_credentials_content %}
            <div class="swag-migration-wizard__content">
                {% block own_profile_page_credentials_information %}
                    <div class="swag-migration-wizard__content-information">
                        {% block own_profile_page_credentials_local_hint %}
                            {{ $tc('swag-migration.wizard.pages.credentials.shopware55.local.contentInformation') }}
                        {% endblock %}
                    </div>
                {% endblock %}

                {% block own_profile_page_credentials_credentials %}
                    <div class="swag-migration-wizard__form">
                        {% block own_profile_page_credentials_local_db_host_port_group %}
                            <sw-container columns="1fr 80px"
                                          gap="16px">
                                {% block own_profile_page_credentials_local_dbhost_field %}
                                    <sw-text-field v-autofocus
                                                   name="sw-field--dbHost"
                                                   :label="$tc('swag-migration.wizard.pages.credentials.shopware55.local.dbHostLabel')"
                                                   :placeholder="$tc('swag-migration.wizard.pages.credentials.shopware55.local.dbHostPlaceholder')"
                                                   v-model="inputCredentials.dbHost">
                                    </sw-text-field>
                                {% endblock %}

                                {% block own_profile_page_credentials_local_dbport_field %}
                                    <sw-field name="sw-field--dbPort"
                                              :label="$tc('swag-migration.wizard.pages.credentials.shopware55.local.dbPortLabel')"
                                              v-model="inputCredentials.dbPort">
                                    </sw-field>
                                {% endblock %}
                            </sw-container>
                        {% endblock %}

                        {% block own_profile_page_credentials_local_dbuser_field %}
                            <sw-field name="sw-field--dbUser"
                                      :label="$tc('swag-migration.wizard.pages.credentials.shopware55.local.dbUserLabel')"
                                      :placeholder="$tc('swag-migration.wizard.pages.credentials.shopware55.local.dbUserPlaceholder')"
                                      v-model="inputCredentials.dbUser">
                            </sw-field>
                        {% endblock %}

                        {% block own_profile_page_credentials_local_dbpassword_field %}
                            <sw-field name="sw-field--dbPassword"
                                      type="password"
                                      :label="$tc('swag-migration.wizard.pages.credentials.shopware55.local.dbPasswordLabel')"
                                      :placeholder="$tc('swag-migration.wizard.pages.credentials.shopware55.local.dbPasswordPlaceholder')"
                                      v-model="inputCredentials.dbPassword">
                            </sw-field>
                        {% endblock %}

                        {% block own_profile_page_credentials_local_dbname_field %}
                            <sw-field name="sw-field--dbName"
                                      :label="$tc('swag-migration.wizard.pages.credentials.shopware55.local.dbNameLabel')"
                                      :placeholder="$tc('swag-migration.wizard.pages.credentials.shopware55.local.dbNamePlaceholder')"
                                      v-model="inputCredentials.dbName">
                            </sw-field>
                        {% endblock %}
                    </div>
                {% endblock %}
            </div>
        {% endblock %}
    </div>
{% endblock %}
```

A few things to notice: The component name isn't random, it has to consist of:
1. The prefix: `swag-migration-profile-`
2. The name of the profile
3. The name of the gateway
4. The suffix: `-credential-form`

To see your credentials page, you have to register this component in your `main.js`:

```js
import './src/own-profile/profile';
``` 

## Creating a DataSet and DataSelection

Now the credentials page is loaded in the administration and the connection check will succeed. But there is no data selection,
if you open the data selection table. To add an entry to this table, you have to create a `ProductDataSet` first:

```php
<?php declare(strict_types=1);

namespace SwagMigrationOwnProfileExample\Profile\OwnProfile\DataSelection\DataSet;

use SwagMigrationAssistant\Migration\DataSelection\DataSet\CountingInformationStruct;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\CountingQueryStruct;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\DataSet;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationOwnProfileExample\Profile\OwnProfile\OwnProfile;

class ProductDataSet extends DataSet
{
    /**
     * Returns the entity identifier of this DataSet
     */
    public static function getEntity(): string
    {
        return 'product';
    }

    /**
     * Supports only an OwnProfile
     */
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OwnProfile;
    }

    /**
     *  Count information: Count product table
     */
    public function getCountingInformation(): ?CountingInformationStruct
    {
        $information = new CountingInformationStruct(self::getEntity());
        $information->addQueryStruct(new CountingQueryStruct('product'));

        return $information;
    }
}
```

Now you have to use this `ProductDataSet` in the new `ProductDataSelection`:

```php
<?php declare(strict_types=1);

namespace SwagMigrationOwnProfileExample\Profile\OwnProfile\DataSelection;

use SwagMigrationAssistant\Migration\DataSelection\DataSelectionInterface;
use SwagMigrationAssistant\Migration\DataSelection\DataSelectionStruct;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationOwnProfileExample\Profile\OwnProfile\DataSelection\DataSet\ProductDataSet;
use SwagMigrationOwnProfileExample\Profile\OwnProfile\OwnProfile;

class ProductDataSelection implements DataSelectionInterface
{
    /**
     * Identifier of this DataSelection
     */
    public const IDENTIFIER = 'products';

    /**
     * Supports only an OwnProfile
     */
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OwnProfile;
    }

    public function getData(): DataSelectionStruct
    {
        return new DataSelectionStruct(
            self::IDENTIFIER,
            $this->getEntityNames(),
            $this->getEntityNamesRequiredForCount(),
            /*
             * Snippet of the original ProductDataSelection, if you
             * want to use your own title, you have to create a new snippet
             */
            'swag-migration.index.selectDataCard.dataSelection.products',
            100
        );
    }

    /**
     * Return all entity names, which should be migrated with this DataSelection
     *
     * @return string[]
     */
    public function getEntityNames(): array
    {
        return [
            ProductDataSet::getEntity()
        ];
    }


    public function getEntityNamesRequiredForCount(): array
    {
        return $this->getEntityNames();
    }
}
```

To see the created `ProductDataSelection` in the administration, you have to register it both in the `services.xml` and tag
them with `shopware.migration.data_selection` and `shopware.migration.data_set`:

```xml
<service id="SwagMigrationOwnProfileExample\Profile\OwnProfile\DataSelection\ProductDataSelection">
    <tag name="shopware.migration.data_selection"/>
</service>

<service id="SwagMigrationOwnProfileExample\Profile\OwnProfile\DataSelection\DataSet\ProductDataSet">
    <tag name="shopware.migration.data_set"/>
</service>
```

## Creating a product gateway reader

Currently, you can see the `DataSelection` in the administration, but if you select it and start a migration, there
will be no product migrated. That's because the gateway `read` function isn't implemented, yet. But before you can implement
this function, you have to create a new `ProductReader` first:

```php
<?php declare(strict_types=1);

namespace SwagMigrationOwnProfileExample\Profile\OwnProfile\Gateway\Reader;

use Doctrine\DBAL\Connection;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Profile\ReaderInterface;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Connection\ConnectionFactoryInterface;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Local\Reader\LocalReaderInterface;
use SwagMigrationOwnProfileExample\Profile\OwnProfile\DataSelection\DataSet\ProductDataSet;
use SwagMigrationOwnProfileExample\Profile\OwnProfile\OwnProfile;

class ProductReader implements ReaderInterface, LocalReaderInterface
{
    /**
     * @var ConnectionFactoryInterface
     */
    private $connectionFactory;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(ConnectionFactoryInterface $connectionFactory)
    {
        $this->connectionFactory = $connectionFactory;
    }

    /**
     * Supports only an OwnProfile and the ProductDataSet
     */
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OwnProfile
            && $migrationContext->getDataSet()::getEntity() === ProductDataSet::getEntity();
    }

    /**
     * Creates a database connection and sets the connection class variable
     */
    protected function setConnection(MigrationContextInterface $migrationContext): void
    {
        $this->connection = $this->connectionFactory->createDatabaseConnection($migrationContext);
    }

    /**
     * Fetches all entities out of the product table with the given limit
     */
    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $query = $this->connection->createQueryBuilder();
        $query->from('product');
        $query->addSelect('*');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
}
```

Then you have to register this in `services.xml` and tag it with `shopware.migration.local_reader`:

```xml
<service id="SwagMigrationOwnProfileExample\Profile\OwnProfile\Gateway\Reader\ProductReader">
    <argument type="service" id="SwagMigrationAssistant\Profile\Shopware\Gateway\Connection\ConnectionFactory"/>
    <tag name="shopware.migration.local_reader"/>
</service>
```

Once the `ProductReader` is created and registered, you can use it in the `read` method of the `OwnLocaleGateway`:

```php
<?php declare(strict_types=1);

namespace SwagMigrationOwnProfileExample\Profile\OwnProfile\Gateway;

use Shopware\Core\Framework\Context;
use SwagMigrationAssistant\Migration\EnvironmentInformation;
use SwagMigrationAssistant\Migration\Gateway\GatewayInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\RequestStatusStruct;
use SwagMigrationAssistant\Profile\Shopware\Exception\DatabaseConnectionException;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Connection\ConnectionFactoryInterface;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Local\ReaderRegistry;
use SwagMigrationAssistant\Profile\Shopware\Gateway\TableCountReaderInterface;
use SwagMigrationOwnProfileExample\Profile\OwnProfile\OwnProfile;

class OwnLocaleGateway implements GatewayInterface
{
    public const GATEWAY_NAME = 'local';

    private $connectionFactory;

    /**
     * @var TableCountReaderInterface
     */
    private $localTableCountReader;

    /**
     * @var ReaderRegistry
     */
    private $readerRegistry;

    public function __construct(
        ConnectionFactoryInterface $connectionFactory,
        TableCountReaderInterface $localTableCountReader,
        ReaderRegistry $readerRegistry
    ) {
        $this->connectionFactory = $connectionFactory;
        $this->localTableCountReader = $localTableCountReader;
        $this->readerRegistry = $readerRegistry;
    }

    public function getName(): string
    {
        return self::GATEWAY_NAME;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OwnProfile;
    }

    public function read(MigrationContextInterface $migrationContext): array
    {
        $reader = $this->readerRegistry->getReader($migrationContext);

        return $reader->read($migrationContext);
    }

    public function readEnvironmentInformation(
        MigrationContextInterface $migrationContext,
        Context $context
    ): EnvironmentInformation {
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

        $totals = $this->readTotals($migrationContext, $context);

        return new EnvironmentInformation(
            $profile->getSourceSystemName(),
            $profile->getVersion(),
            'Example Host Name',
            $totals,
            [],
            new RequestStatusStruct(),
            false
        );
    }

    public function readTotals(MigrationContextInterface $migrationContext, Context $context): array
    {
        return $this->localTableCountReader->readTotals($migrationContext, $context);
    }
}
```

## Creating a converter

By using the gateway reader, you fetch all products, but don't use this data yet. In this step you implement the logic of the converter:

```php
<?php declare(strict_types=1);

namespace SwagMigrationOwnProfileExample\Profile\OwnProfile\Converter;

use Shopware\Core\Framework\Context;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Converter\ShopwareConverter;
use SwagMigrationOwnProfileExample\Profile\OwnProfile\DataSelection\DataSet\ProductDataSet;
use SwagMigrationOwnProfileExample\Profile\OwnProfile\OwnProfile;

class ProductConverter extends ShopwareConverter
{
    /**
     * @var string
     */
    private $connectionId;

    /**
     * @var Context
     */
    private $context;

    public function getSourceIdentifier(array $data): string
    {
        return $data['id'];
    }

    /**
     * Supports only an OwnProfile and the ProductDataSet
     */
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof OwnProfile &&
            $migrationContext->getDataSet()::getEntity() === ProductDataSet::getEntity();
    }

    /**
     * Writes the created mapping
     */
    public function writeMapping(Context $context): void
    {
        $this->mappingService->writeMapping($context);
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $this->connectionId = $migrationContext->getConnection()->getId();
        $this->context = $context;

        /**
         * Gets the product uuid out of the mapping table or creates a new one
         */
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $migrationContext->getConnection()->getId(),
            ProductDataSet::getEntity(),
            $data['id'],
            $context,
            $this->checksum
        );

        $converted['id'] = $this->mainMapping['entityUuid'];
        $this->convertValue($converted, 'productNumber', $data, 'product_number');
        $this->convertValue($converted, 'name', $data, 'product_name');
        $this->convertValue($converted, 'stock', $data, 'stock', self::TYPE_INTEGER);

        if (isset($data['tax'])) {
            $converted['tax'] = $this->getTax($data);
            $converted['price'] = $this->getPrice($data, $converted['tax']['taxRate']);
        }

        unset(
          $data['id'],
          $data['product_number'],
          $data['product_name'],
          $data['stock'],
          $data['tax'],
          $data['price']
        );

        if (empty($data)) {
            $data = null;
        }
        $this->updateMainMapping($migrationContext, $context);

        return new ConvertStruct($converted, $data, $this->mainMapping['id']);
    }

    private function getTax(array $data): array
    {
        $taxRate = (float) $data['tax'];

        /**
         * Gets the tax uuid by the given tax rate
         */
        $taxUuid = $this->mappingService->getTaxUuid($this->connectionId, $taxRate, $this->context);

        /**
         * If no tax rate is found, create a new one
         */
        if ($taxUuid === null) {
            $mapping = $this->mappingService->createMapping(
                $this->connectionId,
                DefaultEntities::TAX,
                $data['id']
            );
            $taxUuid = $mapping['entityUuid'];
        }

        return [
            'id' => $taxUuid,
            'taxRate' => $taxRate,
            'name' => 'Own profile tax rate (' . $taxRate . ')',
        ];
    }

    private function getPrice(array $data, float $taxRate): array
    {
        $gross = (float) $data['price'] * (1 + $taxRate / 100);

        /**
         * Gets the currency uuid by the given iso code
         */
        $currencyUuid = $this->mappingService->getCurrencyUuid(
            $this->connectionId,
            'EUR',
            $this->context
        );

        if ($currencyUuid === null) {
            return [];
        }

        $price = [];
        $price[] = [
            'currencyId' => $currencyUuid,
            'gross' => $gross,
            'net' => (float) $data['price'],
            'linked' => true,
        ];

        return $price;
    }
}
```

To use this converter, you must register it in the `services.xml`:

```xml
<service id="SwagMigrationOwnProfileExample\Profile\OwnProfile\Converter\ProductConverter">
    <argument type="service" id="SwagMigrationAssistant\Migration\Mapping\MappingService"/>
    <argument type="service" id="SwagMigrationAssistant\Migration\Logging\LoggingService"/>
    <tag name="shopware.migration.converter"/>
</service>
```

To write new entities, you have to create a new writer class, but for the product entity, you can use the `ProductWriter`:

```php
<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Migration\Writer;

use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;

class ProductWriter extends AbstractWriter
{
    public function supports(): string
    {
        return DefaultEntities::PRODUCT;
    }
}
```

This writer will automatically be called, because the `getEntityName` method of your `ProductDataSet` is compared with
the return value of the `supports` method of the writer in the `WriterRegistry`. These values are identically and so the writer will be used to
write your product entities.

## Source
 
 There's a GitHub repository available, containing a full example source.
 Check it out [here](https://github.com/shopware/swag-docs-create-migration-profile).
