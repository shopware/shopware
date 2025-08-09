---
title: Message queue statistics
issue: NEXT-12345

---
# Core
* Added `Shopware\Core\Framework\MessageQueue\Stamp\SentAtStamp` for tracking when messages enter the queue
* Added `Shopware\Core\Framework\MessageQueue\Middleware\QueuedTimeMiddleware` for adding `SentAtStamp` to all sent messages
* Added `Shopware\Core\Framework\MessageQueue\Subscriber\MessageQueueStatsSubscriber` for storing statistics on message processing
* Added `Shopware\Core\Framework\MessageQueue\Stats\StatsService` for tracking message queue statistics
* Added repository abstractions to support different types of storage implementations
* Added `Shopware\Core\Framework\MessageQueue\Stats\MySQLStatsRepository` MySQL repository implementation
* Added `messenger_stats` database table for storing message processing data
* Added configuration options `shopware.messenger.stats.enabled` and `shopware.messenger.stats.time_span` for statistics configuration

___
# API
* Added endpoint `GET /api/_info/message-stats.json` for retrieving message queue statistics

___
# Administration
* Added service `sw-settings-message-stats` for providing access to message queue statistics API
* Added settings page in the administration for displaying message queue statistics
