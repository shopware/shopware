<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Plugin;

use AsyncAws\Core\Result;
use AsyncAws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class CopyBatch
{
    public static function copy(FilesystemOperator $filesystem, CopyBatchInput ...$files): void
    {
        $s3Client = self::getS3Client($filesystem);

        if ($s3Client) {
            self::copyS3($s3Client[0], $s3Client[1], ...$files);

            return;
        }

        foreach ($files as $batchInput) {
            $handle = $batchInput->getSourceFile();
            if (\is_string($handle)) {
                $handle = fopen($handle, 'r');
            }

            foreach ($batchInput->getTargetFiles() as $targetFile) {
                $filesystem->writeStream($targetFile, $handle);
            }

            if (\is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * Extract the S3 client from the filesystem operator.
     *
     * @return array{0: AsyncAwsS3Adapter, 1: S3Client}|null
     */
    private static function getS3Client(FilesystemOperator $operator): ?array
    {
        if (!class_exists(AsyncAwsS3Adapter::class) || !$operator instanceof Filesystem) {
            return null;
        }

        $func = \Closure::bind(fn () => $operator->adapter, $operator, $operator::class);

        $adapter = $func();

        if (!$adapter instanceof AsyncAwsS3Adapter) {
            return null;
        }

        $func = \Closure::bind(fn () => $adapter->client, $adapter, $adapter::class);

        return [$adapter, $func()];
    }

    /**
     * We use the S3 client directly to copy the files in batches and copy the files in parallel.
     * This is necessary because the Flysystem does not support copying files in parallel or async.
     */
    private static function copyS3(AsyncAwsS3Adapter $adapter, S3Client $s3Client, CopyBatchInput ...$files): void
    {
        // Extract the bucket name, mime type detector and path prefixer from the adapter.
        $bucketName = \Closure::bind(fn () => $adapter->bucket, $adapter, $adapter::class)();

        $mimeTypeDetector = \Closure::bind(fn () => $adapter->mimeTypeDetector, $adapter, $adapter::class)();

        $prefixer = \Closure::bind(fn () => $adapter->prefixer, $adapter, $adapter::class)();

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
