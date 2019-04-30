[titleEn]: <>(Refactored plugin entity)

We refactored the plugin entity as follows:

* Renamed plugin.name => plugin.baseClass
    * The plugin.baseClass property holds the fully qualified domain name (FQDN)
* Added plugin.name
    * The plugin.name holds the technical plugin name