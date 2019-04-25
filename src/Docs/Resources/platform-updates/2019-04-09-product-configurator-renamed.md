[titleEn]: <>(Product table renaming)

We renamed the following tables as follow:

* product.variatios => product.options
  * Relation between variants and their options which used for the generation.
*product.configurators => product.configuratorSettings
  * Relation between products and the configurator settings. This table are used for the administration configurator wizard
* product.datasheet => product.properties
  * Relation between products and their property options. This options are not related to physical variants with own order numbers
* configuration_group => property_group
  * Defines a group for possible options like color, size, ...
* configuration_group_option => property_group_option

All related api routes and associations are renamed too:

* /api/v1/property-group
* /api/v1/property-group-option
* /api/v1/product/{id}/options
* ...

Detail changes can be found here: https://github.com/shopware/platform/commit/1d8af890792df21bed13ef94afa1ac684d6d7f7d
