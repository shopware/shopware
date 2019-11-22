[titleEn]: <>(Changed loading order of plugins)

The order of the loaded plugins has changed.
Before, the plugins has been sorted by the ID column.
Because of the UUIDs stored here, the order was random and changed on every (re)installation of Shopware.

From now on, the plugins are loaded in the order of their installation date.
So the first installed plugin is the first, which is initialised on kernel boot.

This is temporary solution, as this will not fully resolve dependencies problems between plugins.
There are already tickets open, which have the goal to improve the whole plugin loading process.
