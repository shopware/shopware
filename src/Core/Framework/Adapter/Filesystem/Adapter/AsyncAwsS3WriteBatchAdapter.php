<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use AsyncAws\Core\Result;
use AsyncAws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\WriteBatchInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AsyncAwsS3WriteBatchAdapter extends AsyncAwsS3Adapter implements WriteBatchInterface
{
    public function writeBatch(CopyBatchInput ...$files): void
    {
        /** @var S3Client $s3Client */
        $s3Client = \Closure::bind(fn () => $this->client, $this, parent::class)();

        // Extract the bucket name, mime type detector and path prefixer from the adapter.
        $bucketName = \Closure::bind(fn () => $this->bucket, $this, parent::class)();

        $mimeTypeDetector = \Closure::bind(fn () => $this->mimeTypeDetector, $this, parent::class)();

        $prefixer = \Closure::bind(fn () => $this->prefixer, $this, parent::class)();

        // Copy the files in batches of 250 files. This is necessary to have open sockets and not run into the "Too many open files" error.
        foreach (array_chunk($files, 250) as $filesBatch) {
            $requests = [];

            foreach ($filesBatch as $file) {
                $sourceFile = $file->getSourceFile();

                if (\is_string($sourceFile)) {
                    $sourceFile = @fopen($sourceFile, 'rb');

                    if ($sourceFile === false) {
                        continue;
                    }
                }

                $mimeType = $mimeTypeDetector->detectMimeType($file->getTargetFiles()[0], $sourceFile);

                foreach ($file->getTargetFiles() as $targetFile) {
                    $options = [
                        'Bucket' => $bucketName,
                        'Key' => $prefixer->prefixPath($targetFile),
                        'Body' => $sourceFile,
                    ];

                    if ($mimeType !== null) {
                        $options['ContentType'] = $mimeType;
                    }

                    $requests[] = $s3Client->putObject($options);
                }
            }

            // Resolve the requests in parallel.
            foreach (Result::wait($requests) as $result) {
                $result->resolve();
            }

            // Make sure all handles are closed. To free up the sockets.
            foreach ($filesBatch as $file) {
                if (\is_resource($file->getSourceFile())) {
                    fclose($file->getSourceFile());
                }
            }
        }
    }
}
