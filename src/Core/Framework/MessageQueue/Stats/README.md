# Message Queue Statistics

This feature provides statistics about processed messages in the Shopware message queue system. It tracks message processing metrics and provides insights into message queue performance.

## Features

- Tracks total number of processed messages
- Calculates average time messages spend in the queue
- Provides statistics per message type
- Maintains historical data within a configurable time span
- Exposes statistics via API endpoint

## Message Tracking Mechanism

The statistics collection is enabled by the `QueuedTimeMiddleware`, which is automatically registered in the message bus configuration. This middleware:

1. Adds a `SentAtStamp` to each message when it enters the queue
2. Only adds the stamp if:
   - The message doesn't already have a `SentAtStamp`
   - The message doesn't have a `ReceivedStamp` (indicating that middleware is in the receive phase)
3. The `StatsService` uses this timestamp to calculate:
   - How long messages spend in the queue
   - When messages were processed
   - Message type distribution

## API Endpoint

The statistics are available via the following endpoint:
```
GET /api/_info/message-stats.json
```

### Response Format

```json
{
    "extensions": [],
    "enabled": true,
    "totalMessagesProcessed": 6,
    "processedSince": "2025-04-15T15:08:42.000+00:00",
    "averageTimeInQueue": 11.1667,
    "messageTypeStats": [
        {
            "extensions": [],
            "type": "Shopware\\Core\\Framework\\Adapter\\Cache\\InvalidateCacheTask",
            "count": 1
        },
        {
            "extensions": [],
            "type": "Shopware\\Elasticsearch\\Framework\\Indexing\\CreateAliasTask",
            "count": 1
        },
        {
            "extensions": [],
            "type": "Shopware\\Core\\Content\\ProductExport\\ScheduledTask\\ProductExportGenerateTask",
            "count": 4
        }
    ]
}
```

## Implementation Details

### Components

1. **StatsService**
   - Main service for interacting with message statistics
   - Registers new messages and their processing time
   - Retrieves aggregated statistics

2. **Repository Implementations**
   - **MySQLStatsRepository**
     - Implements message statistics storage in MySQL database
     - Maintains a `messenger_stats` table with message processing data
     - Automatically cleans up old data based on configured time span
     - Limits the number of tracked message types to 100
   - Other repository implementations may have different limits for tracked message types

3. **QueuedTimeMiddleware**
   - Automatically adds timestamps to messages entering the queue
   - Enables tracking of message processing time
   - Integrated into the default message bus configuration

### Database Schema

The `messenger_stats` table contains the following columns:
- `message_type`: Fully qualified class name of the message
- `time_in_queue`: Time in seconds the message spent in the queue
- `created_at`: Timestamp when the message was processed

### Configuration

The message queue statistics can be configured in your `config/packages/shopware.yaml` file:

```yaml
shopware:
    messenger:
        stats:
            enabled: true    # Enable or disable message statistics collection
            time_span: 300   # Time span in seconds for which statistics are maintained (default: 300)
```

## Usage

The statistics are automatically collected for all messages processed through the message queue system. No additional configuration is required to start collecting statistics.

## Limitations

- Each repository implementation may have its own limit for tracked message types (e.g., MySQL repository limits to 100 message types)
- Historical data is automatically cleaned up based on the configured time span
- Only messages with a `SentAtStamp` are tracked (automatically added by QueuedTimeMiddleware)
- Statistics are only collected for messages processed through the configured message bus
