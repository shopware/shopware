[titleEn]: <>(Profile and Connection)

Users of the plugin can create connections to different source systems.
A connection is used to allow multiple migrations from the same source and update the right data (mapping).
Connections require a specific profile, indicating the type of source system.
Users can, for example, create a connection to a Shopware shop using the Shopware 5.5 profile.
Developers are able to create their own profiles from scratch and connect to different source systems or just build up on and extend existing ones.

## Profile
The base of Shopware Migration Assistant is the profile, which enables you to migrate your shop system to Shopware 6.
Shopware Migration Assistant comes with the default Shopware 5.5 profile and is located in the shopware55.xml:

```xml
<service id="SwagMigrationNext\Profile\Shopware55\Shopware55Profile">
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter"/>
    <argument type="service" id="SwagMigrationNext\Migration\Converter\ConverterRegistry"/>
    <argument type="service" id="SwagMigrationNext\Migration\Media\MediaFileService"/>
    <argument type="service" id="SwagMigrationNext\Migration\Logging\LoggingService"/>
    <argument type="service" id="SwagMigrationNext\Migration\Data\SwagMigrationDataDefinition"/>
    <tag name="shopware.migration.profile"/>
</service>
```

In order to identify itself, the profile has to implement a `getName` function, that returns the unique name of the profile.
The profile is used to control the following actions:
1. Fetching of all data from the source system via [gateway](./060-gateway-and-reader.md)
2. Converting data from source system to Shopware 6 structure via [converter](./070-converter-and-mapping.md)
3. Reading environment information from source system e.g. shop structure and entity totals

```php
<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Profile\Shopware55;

use Shopware\Core\Framework\Context;
use SwagMigrationAssistant\Migration\EnvironmentInformation;
use SwagMigrationAssistant\Migration\Gateway\GatewayInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Profile\ProfileInterface;

class Shopware55Profile implements ProfileInterface
{
    public const PROFILE_NAME = 'shopware55';

    public const SOURCE_SYSTEM_NAME = 'Shopware';

    public const SOURCE_SYSTEM_VERSION = '5.5';

    /* .. */

    public function getName(): string
    {
        return self::PROFILE_NAME;
    }

    public function convert(array $data, MigrationContextInterface $migrationContext, Context $context): int
    {
        
        /* .. */

        return $writtenCount;
    }

    public function readEnvironmentInformation(GatewayInterface $gateway, MigrationContextInterface $migrationContext): EnvironmentInformation
    {
        
        /* .. */
        
        return $environmentInformation;
    }
}
```

## Connection
To connect Shopware 6 to your source system (e.g. Shopware 5), you will need a connection entity. The connection
includes all important information for your migration run. It contains the credentials for the API or database access,
the actual [premapping](./050-premapping.md) and the profile, [gateway](./060-gateway-and-reader.md) combination which is used for your migration:

 ```php
 <?php declare(strict_types=1);
 
 namespace SwagMigrationAssistant\Migration\Connection;
 
 /*...*/
 
 class SwagMigrationConnectionDefinition extends EntityDefinition
 {
     /*...*/
 
     protected function defineFields(): FieldCollection
     {
         return new FieldCollection([
             (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
             (new StringField('name', 'name'))->setFlags(new Required()),
             (new JsonField('credential_fields', 'credentialFields'))->setFlags(new WriteProtected(MigrationContext::SOURCE_CONTEXT)),
             new JsonField('premapping', 'premapping'),
             (new FkField('profile_id', 'profileId', SwagMigrationProfileDefinition::class))->setFlags(new Required()),
             new CreatedAtField(),
             new UpdatedAtField(),
             new ManyToOneAssociationField('profile', 'profile_id', SwagMigrationProfileDefinition::class, 'id', true),
             new OneToManyAssociationField('runs', SwagMigrationRunDefinition::class, 'connection_id'),
             new OneToManyAssociationField('mappings', SwagMigrationMappingDefinition::class, 'connection_id'),
             new OneToManyAssociationField('settings', GeneralSettingDefinition::class, 'selected_connection_id'),
         ]);
     }
 }
 ```