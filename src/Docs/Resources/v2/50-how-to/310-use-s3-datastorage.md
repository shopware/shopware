[titleEn]: <>(Using AWS S3 as file storage)
[metaDescriptionEn]: <>(Cloud solutions are often the preferred way to store large amounts of files. This article shows you how to configure a cloud file storage, in this example two S3 buckets.)
[hash]: <>(article:how_to_s3)

## Overview

Shopware 6 can be used with several cloud storage providers, it uses
[Flysystem](https://flysystem.thephpleague.com/docs/) to provide a common
interface between different providers as well as the local filesystem. This
enables your shops to read and write files through a common interface.

## Setup

For our example you will need two [S3](https://aws.amazon.com/s3/) buckets:

- One for private files: invoices, delivery notes, etc
- One for public files: product pictures, media files in general

Also you need an [IAM](https://aws.amazon.com/iam/) user to enable your Shopware 6 installation to
access both buckets. Make sure that the bucket permissions are set so that the files from the public
bucket can be read without user credentials and that the private Bucket can only be accessed from
authorized IAM users.

## Configuration

The configuration for file storage of Shopware 6 resides in the general bundle configuration:
```
<development root>
└── config
   └── packages
      └── shopware.yml
```

To set up a non default filesystem for your shop you need to add the `filesystem:` map to 
the `shopware.yml`. Under this key you can separately define your storage for the public and private
filesystem like this:
```yaml
shopware:
  filesystem:
    public:
      type: "amazon-s3"
      config:
        bucket: "{your-public-bucket-name}"
        region: "{your-bucket-region}"
        endpoint: "{your-s3-provider-endpoint}"
        options:
          visibility: "public"
    private:
      type: "amazon-s3"
      config:
        bucket: "{your-private-bucket-name}"
        region: "{your-bucket-region}"
        options:
          visibility: "private"
```

To utilize your public bucket you have to add its URL as the CDN for your shop, this is just another
set of values in the `shopware.yml`:
```yaml
shopware:
  cdn:
    url: "https://s3.{your-bucket-region}.amazonaws.com/{your-private-bucket-name}"
    strategy: "md5"
```

## AWS access

Accessing the buckets is controlled through IAM, this means that your Shopware 6 instance needs 
to be assigned an IAM user. To do this you generate an access key and secret pair for your IAM user.
To pass both the key and the secret to Shopware 6 set the environment variables
`AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` through the `.env` file or other AWS related tooling.
Note: make sure your IAM user has read/write permissions for both Buckets.

## Final notes

Although this example uses AWS S3, Shopware 6 also supports using Google Cloud Storage as an external filesystem.
