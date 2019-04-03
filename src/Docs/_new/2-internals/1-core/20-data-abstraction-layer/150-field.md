[titleEn]: <>(Field)

The data abstraction layer supports fields as it's most atomar extnsion point. Usualy a field represents a single column in the database. You need a custom field for 

* custom validation tasks
* custom data representation

Adding a field is a two step process:

1. extend `\Shopware\Core\Framework\DataAbstractionLayer\Field\Field`
2. implement `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface`   

# The field serializer

A `FieldSerializer` handles encoding and decoding of values for the DAL to store or hydrate. It is required that each `FieldSerializer` references the field type it handles. 

# The field class

From a serialitzers point of view a single field is the configuration on wich itz has to act. Usually it contains the name of the field in a Entity class. 