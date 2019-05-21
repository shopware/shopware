[titleEn]: <>(Migration context)

The central data structure of Shopware Migration Assistant is the migration context. The migration context contains following
information:
1. The current connection of migration
2. Identifier of the current run
3. Information on the current processing data ([DataSet](./030-dataSelection-and-dataSet.md))
4. Offset and limit of the current call

```php
<?php declare(strict_types=1);

/* ... */

class MigrationContext extends Struct implements MigrationContextInterface
{
    public function __construct(
            ?SwagMigrationConnectionEntity $connection,
            string $runUuid = '',
            ?DataSet $dataSet = null,
            int $offset = 0,
            int $limit = 0
        ) {
            $this->runUuid = $runUuid;
            $this->connection = $connection;
            $this->dataSet = $dataSet;
            $this->offset = $offset;
            $this->limit = $limit;
        }
        
    /* ... */
    
}
```

If you want to get the profile data, you can use helper methods like `getProfileName` or `getGatewayName`:

```php
<?php declare(strict_types=1);

/* ... */

class MigrationContext extends Struct implements MigrationContextInterface
{
    
    /* ... */
    
    public function getProfileName(): ?string
    {
        if ($this->connection === null) {
            return null;
        }

        return $this->connection->getProfile()->getName();
    }
    
    public function getGatewayName(): ?string
    {
        if ($this->connection === null) {
            return null;
        }

        return $this->connection->getProfile()->getGatewayName();
    }
    
    /* ... */
    
}
```