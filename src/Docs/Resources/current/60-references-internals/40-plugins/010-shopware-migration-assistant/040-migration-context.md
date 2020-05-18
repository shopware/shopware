[titleEn]: <>(Migration context)
[hash]: <>(article:migration_context)

The central data structure of Shopware Migration Assistant is the migration context. The migration context contains following
information:
1. The current connection of migration (which holds the credentials)
2. Current Profile and Gateway instances
3. Identifier of the current run
4. Information on the current processing data ([DataSet](./030-dataSelection-and-dataSet.md))
5. Offset and limit of the current call

```php
<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Migration;

use Shopware\Core\Framework\Struct\Struct;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\DataSet;
use SwagMigrationAssistant\Migration\Gateway\GatewayInterface;
use SwagMigrationAssistant\Migration\Profile\ProfileInterface;

class MigrationContext extends Struct implements MigrationContextInterface
{
    /* ... */

    public function getProfile(): ProfileInterface
    {
        return $this->profile;
    }

    public function getConnection(): ?SwagMigrationConnectionEntity
    {
        return $this->connection;
    }

    public function getRunUuid(): string
    {
        return $this->runUuid;
    }

    public function getDataSet(): ?DataSet
    {
        return $this->dataSet;
    }

    public function setDataSet(DataSet $dataSet): void
    {
        $this->dataSet = $dataSet;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getGateway(): GatewayInterface
    {
        return $this->gateway;
    }
    
    public function setGateway(GatewayInterface $gateway): void
    {
        $this->gateway = $gateway;
    }
}
```
