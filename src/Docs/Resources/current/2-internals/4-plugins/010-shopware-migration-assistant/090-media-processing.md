[titleEn]: <>(Media processing)

To import files to Shopware 6 using the migration, two steps are necessary:
1. Create a media file object (`MediaDefinition` / `media` table)
For more Details take a look at the `MediaConverter`
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
        $this->context = $context;
        $this->locale = $data['_locale'];
        unset($data['_locale']);
        $this->connectionId = $migrationContext->getConnection()->getId();

        $converted = [];
        $converted['id'] = $this->mappingService->createNewUuid(
            $this->connectionId,
            DefaultEntities::MEDIA,
            $data['id'],
            $context
        );

        if (!isset($data['name'])) {
            $data['name'] = $converted['id'];
        }

        // The MediaFileService from the service container. This will register the file for download
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

        $albumUuid = $this->mappingService->getUuid(
          $this->connectionId,
            DefaultEntities::MEDIA_FOLDER,
          $data['albumID'],
          $this->context
        );

        if ($albumUuid !== null) {
            $converted['mediaFolderId'] = $albumUuid;
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

        if (empty($data)) {
            $data = null;
        }

        // The MediaWriter will write this Shopware 6 media object
        return new ConvertStruct($converted, $data);
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

        //Fetch media files from database
        $client = new Client([
            'verify' => false,
        ]);
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('mediaId', $mediaIds));
        $criteria->addFilter(new EqualsFilter('runId', $runId));
        $mediaSearchResult = $this->mediaFileRepo->search($criteria, $context);
        /** @var SwagMigrationMediaFileEntity[] $media */
        $media = $mediaSearchResult->getElements();

        //Do download requests and store the promises
        $promises = $this->doMediaDownloadRequests($media, $fileChunkByteSize, $mappedWorkload, $client);

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
First, the service fetches all media files associated with given media ids and downloads these media files from the source system.
After this, it handles the response, saves the media files in a temporary folder and copies them to Shopware 6 filesystem.
In the end the service sets a `processed` status to these media files, saves all warnings that may have occured and
returns the status of the processed files.
