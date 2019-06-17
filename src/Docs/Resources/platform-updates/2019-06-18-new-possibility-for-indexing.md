[titleEn]: <>(New possibility for indexing)

Up until now you could start all indexer with the help of the `IndexerRegistry` (`IndexerRegistryInterface`).
This way the indexer are run in the current PHP process (for example in the request).

Now it is also possible to index via the `IndexerMessageSender` (`IndexerRegistryInterface`).
This way each indexer will run in the message queue worker and your request can finish much faster.