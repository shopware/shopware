[titleEn]: <>(Migration assistant introduction)
[hash]: <>(article:migration_introduction)

[Shopware Migration Assistant](https://github.com/shopware/SwagMigrationAssistant) was built with simple but powerful concepts in mind.
These enable you to extend the plugin in various ways and migrate data into the Shopware 6 environment.
You should have a basic understanding of how to use the migration plugin and its core features, before extending it yourself.
(this documentation will not explain the usage of the plugin).

We will provide you with a basic introduction into the concepts and structure right here in this chapter.
Take a look at the last headline (Extension points) to find out more about the various ways to extend this plugin.

## Profile and Connections
Users of the plugin can create connections to different source systems.
A connection is used to allow multiple migrations from the same source and update the right data (mapping).
Connections require a specific profile, indicating the type of source system.
Users can, for example, create a connection to a Shopware shop using the Shopware 5.5 profile.
Developers are able to create their own profiles from scratch and connect to different source systems or just build up on and extend existing ones.

For more details have a look at [Profile and Connection](./020-profile-and-connection.md).

## DataSelection and DataSet
These are the fundamental data structures for define what to migrate.
Each `DataSet` represents an entity, for example a database table.
Each `DataSelection` represents an orderly group of `DataSets`.
For more Information take a look at [DataSelection and DataSet](./030-dataSelection-and-dataSet.md).

## Migration context
This data structure provides all necessary data of the migration.

For more details have a look at [migration context](./040-migration-context.md).

## Premapping
Because the structure of the source system does not always match the structure of the target system, the user may need to map the old structure to the new one.
For example, in Shopware 5 we have default salutations like 'mr', but the user can also create custom ones.
In Shopware 6 there are also default salutations like 'mr' and the user can also create custom ones.
So the salutation 'mr' from Shopware 5 must be mapped to Shopware 6 'mr'.
In this default case the mapping can be achieved automatically, but customized salutations will most likely have to be mapped manually.
The premapping will be written into the mapping table to associate the old identifier with the new one.
[More details](./050-premapping.md)

## Gateway and Reader
Users will have to specify a gateway for the connection. The gateway defines the way of communicating with the source system.
Behind the user interface we use `Reader` objects to read the data from the source system.
For the `shopware55` profile we have the `api` gateway, which communicates via http/s with the source system,
and the `local` gateway, which communicates directly with the source system's database. Thus both systems must be on the 
same server for successfully using the `local` gateway.

If you want to use the `ShopwareApiGateway` you have to download the [Shopware Connector](https://github.com/shopware/SwagMigrationConnector)
plugin for your Shopware 5. For more details have a look at the [Gateway and reader](./060-gateway-and-reader.md).

## Converter, Mapping and Deltas
Data gathered by `Reader` objects is transferred to `Converter` objects that put the data in a format Shopware 6 is able to work with.
Simultaneously entries in the underlying mapping table are inserted to map the old identifiers to the new ones for future migrations (Have a look at the `MappingService` for that).
The mapping is saved for the current connection. Converted data will be removed after the migration, the mapping will stay persistent.
Also a checksum is saved to the mapping to identify and skip the same source data (data has not been changed since last migration).
You can find out more about them here: [Converter, mapping and deltas](./070-converter-and-mapping.md)

## Logging
During any migration, especially during the data conversion, there will possibly be errors that should be logged.
The users can see these errors and these should be as helpful as possible.
For more information have a look at [Logging](./071-logging.md).

## Writer
The `Writer` objects will receive the converted data and write it to Shopware 6.
There is no special magic here and you don't need to worry about error handling because the migration assistant takes care of it.
To learn more about them take a look at [Writer](./080-writer.md).

## Media processing
During a typical migration we download the media files from the source system to Shopware 6.
This is the last processing step in the migration and may be done differently for other gateways.
For example the `local` gateway will copy and rename the files directly in the local filesystem.
For more Details you can look at [Media processing](./090-media-processing.md).

## After migration
All fetched data will be deleted after finishing or aborting a migration run, but the mapping of the identifiers will stay.

## The migration procedure
The following bullet points will give you a general overview of what happens during a common migration.
1. The user selects / creates a connection (with a profile and gateway specified)
2. The user selects some of the available data (`DataSelections`)
3. Premapping check / execution: The user maps data from the source system to the current system
(These decisions are stored with the connection.)
4. Fetch data for every `DataSet` in every selected `DataSelection`
(mapping is used to store / use the identifiers from the source system.)  
    4.1 The corresponding `Reader` reads the data  
    4.2 The corresponding `Converter` converts the data  
5. Write data for every `DataSet` in every selected `DataSelection`  
    5.1 The corresponding `Writer` writes the data
6. Process media, if necessary for example to download / copy images
    6.1 Data in `swag_migration_media_file` table will be downloaded / copied  
    6.2 Files are assigned to media objects in Shopware 6
7. Finish migration to cleanup

These steps can be done multiple times. Each migration is called a `Run` / `MigrationRun` and will be saved to let the users know about any errors that occurred (in form of a detailed history).

## Extension points
The recommended way to migrate plugin data from a source system is to extend that profile by a new `DataSelection`.
It is also possible to create a new profile, in case a migration from a different shop / source system is sought.

Take a look at the following HowTos for your scenario to get a step by step tutorial:
- [Extending a Shopware migration profile](./../../../4-how-to/520-extend-shopware-migration-profile.md) <- migrating your first basic plugin data (via local gateway)
- [Extending the Migration Connector](./../../../4-how-to/530-extend-shopware-migration-connector.md) <- add API support for your migration
- [Decorating a Shopware Migration Assistant converter](./../../../4-how-to/550-decorate-shopware-migration-converter.md) <- implement a premapping and change the behavior of an existing converter
- [Creating a new migration profile](./../../../4-how-to/600-create-migration-profile.md) <- create a new profile from scratch to support a third party source system (other than Shopware)
