[titleEn]: <>(Media processing)
[hash]: <>(article:migration_media)

To import files to Shopware 6 using the migration, two steps are necessary:
1. Create a media file object (`MediaDefinition` / `media` table)
For more details take a look at the `MediaConverter`
2. Create an entry in the `SwagMigrationMediaFileDefinition` / `swag_migration_media_file` table.

Every entry in the `swag_migration_media_file` table of the associated migration run will get processed by an implementation
of `MediaFileProcessorInterface`. For the `api` gateway the `HttpMediaDownloadService` is used and will download
the files via HTTP.

To add a file to the table you can do something like this in your `Converter` class (this example is from the `MediaConverter`):
```php
<?php declare(strict_types=1);

abstract class MediaConverter extends ShopwareConverter
{
    /* ... */

    public function convert(
        array $data,
        Context $context,
        MigrationContextInterface $migrationContext
    ): ConvertStruct {
        $this->generateChecksum($data);
        $this->context = $context;
        $this->locale = $data['_locale'];
        unset($data['_locale']);

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        $converted = [];
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::MEDIA,
            $data['id'],
            $context,
            $this->checksum
        );
        $converted['id'] = $this->mainMapping['entityUuid'];

        if (!isset($data['name'])) {
            $data['name'] = $converted['id'];
        }

        $this->mediaFileService->saveMediaFile(
            [
                'runId' => $migrationContext->getRunUuid(),
                'entity' => MediaDataSet::getEntity(), // important to distinguish between private and public files
                'uri' => $data['uri'] ?? $data['path'],
                'fileName' => $data['name'], // uri or path to the file (because of the different implementations of the gateways)
                'fileSize' => (int) $data['file_size'],
                'mediaId' => $converted['id'], // uuid of the media object in Shopware 6
            ]
        );
        unset($data['uri'], $data['file_size']);

        $this->getMediaTranslation($converted, $data);
        $this->convertValue($converted, 'title', $data, 'name');
        $this->convertValue($converted, 'alt', $data, 'description');

        $albumMapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::MEDIA_FOLDER,
            $data['albumID'],
            $this->context
        );

        if ($albumMapping !== null) {
            $converted['mediaFolderId'] = $albumMapping['entityUuid'];
            $this->mappingIds[] = $albumMapping['id'];
        }

        unset(
            $data['id'],
            $data['albumID'],

            // Legacy data which don't need a mapping or there is no equivalent field
            $data['path'],
            $data['type'],
            $data['extension'],
            $data['file_size'],
            $data['width'],
            $data['height'],
            $data['userID'],
            $data['created']
        );

        $returnData = $data;
        if (empty($returnData)) {
            $returnData = null;
        }
        $this->updateMainMapping($migrationContext, $context);

        // The MediaWriter will write this Shopware 6 media object
        return new ConvertStruct($converted, $returnData, $this->mainMapping['id']);
    }
        
    /* ... */
}
```
`swag_migration_media_files` are processed by the right processor service. This service is different for documents and normal media, but it still is gateway dependent.
For example the `HttpMediaDownloadService` works like this:
```php
<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Profile\Shopware55\Media;

/* ... */

class HttpMediaDownloadService implements MediaFileProcessorInterface
{
    /* ... */

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof ShopwareProfileInterface
            && $migrationContext->getGateway()->getName() === ShopwareApiGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === MediaDataSet::getEntity();
    }

    public function process(MigrationContextInterface $migrationContext, Context $context, array $workload, int $fileChunkByteSize): array
    {
        /* ... */

        //Fetch media from database
        $media = $this->getMediaFiles($mediaIds, $runId, $context);
        
        $client = new Client([
            'verify' => false,
        ]);

        //Do download requests and store the promises
        $promises = $this->doMediaDownloadRequests($media, $mappedWorkload, $client);

        // Wait for the requests to complete, even if some of them fail
        /** @var array $results */
        $results = Promise\settle($promises)->wait();

        /* ... handle responses ... */

        $this->setProcessedFlag($runId, $context, $finishedUuids, $failureUuids);
        $this->loggingService->saveLogging($context);

        return array_values($mappedWorkload);
    }
}
```
First, the service fetches all media files associated with the given media IDs and downloads these media files from the source system.
After this, it handles the response, saves the media files in a temporary folder and copies them to Shopware 6 filesystem.
In the end the service sets a `processed` status to these media files, saves all warnings that may have occurred and
returns the status of the processed files.
