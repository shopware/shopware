# 2021-05-14 - When to use plain SQL or the DAL

## Context

It is often discussed whether to work with plain SQL or with the Data Abstraction Layer.

## Decision

In the following application layers, the DAL should be used for the following reasons:

* In the Store API
    * Data selected and returned via Store API must be extensible by third party developers.
    * Requests against the Store API should always allow additional data to be loaded.
    * Data retrieval and encoding must be secured by ACL.

* Storefront page loader and controller
    * Data passed from the storefront to the Twig templates must be extensible by third party developers.
    * Since our templates are customized by many developers, we cannot provide only a minimal offset of the actual data.

* On admin API level
    * Data selected and returned via admin API must be extensible by third party developers.
    * Requests that go against the Store API should always allow additional data to be loaded.
    * Data retrieval and encoding must be secured by ACL.

* When writing data
    * The DAL has a validation, event and indexing system which is used for the write process. Therefore, it is mandatory to ensure data integrity, that write processes take place exclusively via the DAL.
    * The entity indexers are an exception here, see below.

In the following application layers you should work with plain SQL because of the following reasons:

* In the entity indexers
    * The entity indexers are located behind the entity repository layer, so it only makes sense that they do not work with the repositories but with the database connection directly.
    * the entity indexers must be able to re-index all data after a versions update. To avoid as much hydration and event overhead as possible, they should work directly with the connection.
    * The entity indexers are not an extension point of shopware. The queries that are executed there are only used for internal processing of data and should never be rewritten.

* In Core Components
    * Core components like the theme compiler, request transformer, etc. are not places where a third party developer should be able to load additional data. The data loaded here is for pure processing only and should never be rewritten.
    * Deep processes like theme compiling should not be affected by plugin entity schemas, because plugins are an optional part of the system and might be in an unstable state during an update process.

