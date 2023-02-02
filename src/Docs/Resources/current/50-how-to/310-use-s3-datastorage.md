[titleEn]: <>(Using external storage)
[metaDescriptionEn]: <>(Cloud solutions are often the preferred way to store large amounts of files. This article shows you how to configure a cloud file storage, in this example two S3 buckets.)
[hash]: <>(article:how_to_s3)

## Overview

Shopware 6 can be used with several cloud storage providers, it uses
[Flysystem](https://flysystem.thephpleague.com/docs/) to provide a common
interface between different providers as well as the local filesystem. This
enables your shops to read and write files through a common interface.

The filesystem can be devided into multiple adapters. Each adapter can handle one or more of the following directories: media. sitemaps, .... Of course, you can also use the same configuration for each and all of them.

- One for private files: invoices, delivery notes, plugin files, etc
- One for public files: product pictures, media files, plugin files in general
- One for theme files
- One for sitemap files
- One for bundle assets files

## Configuration

The configuration for file storage of Shopware 6 resides in the general bundle configuration:

```
<development root>
└── config
   └── packages
      └── shopware.yml
```

To set up a non default filesystem for your shop you need to add the `filesystem:` map to 
the `shopware.yml`. Under this key you can separately define your storage for the public, private, theme, sitemap and asset (bundle assets).
```yaml
shopware:
  filesystem:
    public:
      url: "{url-to-your-public-files}"
      # The Adapter Configuration
    private:
      visibility: "private"
      # The Adapter Configuration
    theme:
      url: "{url-to-your-theme-files}"
      # The Adapter Configuration
    asset:
      url: "{url-to-your-asset-files}"
      # The Adapter Configuration
    sitemap:
      url: "{url-to-your-sitemap-files}"
      # The Adapter Configuration
```

## Integrated Adapter Configurations

### Local

```yaml
shopware:
    filesystem:
      {ADAPTER_NAME}:
        type: "local"
        config:
          root: "%kernel.project_dir%/public"
```

### Amazon S3

```yaml
shopware:
    filesystem:
      {ADAPTER_NAME}:
        type: "amazon-s3"
        config:
            bucket: "{your-public-bucket-name}"
            region: "{your-bucket-region}"
            endpoint: "{your-s3-provider-endpoint}"
            root: "{your-root-folder}"
            options:
              visibility: "public" # On private adapters need this to be private
```

For the usage of Minio, consider setting `use_path_style_endpoint` to `true`.

### Google Cloud Platform

```yaml
shopware:
    filesystem:
      {ADAPTER_NAME}:
        type: "google-storage"
        config:
            bucket: "{your-public-bucket-name}"
            projectId: "{your-project-id}"
            keyFilePath: "{path-to-your-keyfile}"
            options:
              visibility: "public" # On private adapters need this to be private
```

## Add your own adapter

To create an own adapter checkout the official Flysystem guide for that [here](https://flysystem.thephpleague.com/v1/docs/advanced/creating-an-adapter/).

To make your adapter available in Shopware you will need to create a AdapterFactory for your Flysystem provided adapter. An example for that could look like this:

```php
<?php

use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;
use League\Flysystem\AdapterInterface;

class MyFlysystemAdapterFactory implements AdapterFactoryInterface
{
    public function getType(): string
    {
        return 'my-adapter-prefix'; // This must match with the type in the yaml file
    }
    
    public function create(array $config): AdapterInterface
    {
        // $config contains the given config from the yaml
        return new MyFlysystemAdapter($config);
    }
}
```

This new class needs to be registered in the DI with the tag `shopware.filesystem.factory` to be useable.
