[titleEn]: <>(CreatedAt and UpdatedAt are set as default)

By extending the EntityDefinition-Class all Definitions now automatically have a `cratedAt`- and `UpdatedAt`-Field, so you don't have to add them manually.
Also every Entity-Struct extending the `Entity`-Class has the associated Properties + Getters and Setters automatically.

The only Exception are `MappingDefinitions`, there these Fields aren't added automatically.

## What do you have to do?
We have extended the `dal:validate`-command to check for fields that don't have a mapped Column.
So run this command to check for Definitions that previously didn`t had these fields.
For those entities you have to write Migrations and add these fields.
For every definitions that has those fields you can remove them from the FieldDefinitions and EntityStructs.