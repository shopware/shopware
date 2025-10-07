---
title: Make AsyncAwsS3WriteBatchAdapter batch size configurable
---
# Core
* Added configurable batch size for `AsyncAwsS3WriteBatchAdapter` via the `shopware.filesystem.batch_write_size` configuration with a default value of 250
* Changed `AwsS3v3Factory` to inject the batch size parameter from configuration
___
# Upgrade Information
## Configuration
You can now configure the batch size for S3 file writing operations in your `config/packages/shopware.yaml`:

```yaml
shopware:
    filesystem:
        batch_write_size: 100  # Default is 250
```

This controls how many files are processed in a single batch when using the AsyncAwsS3WriteBatchAdapter, which helps prevent "Too many open files" errors and allows for performance tuning based on your infrastructure.