<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use AsyncAws\Core\Result;
use AsyncAws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\AsyncAwsS3\PortableVisibilityConverter;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\WriteBatchInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class AsyncAwsS3WriteBatchAdapter extends AsyncAwsS3Adapter implements WriteBatchInterface
{
    /**
     * @var int<1, max>
     */
    public int $batchSize = 250;

    public function writeBatch(CopyBatchInput ...$files): void
    {
        /** @var S3Client $s3Client */
        $s3Client = \Closure::bind(fn () => $this->client, $this, parent::class)();

        // Extract the bucket name, mime type detector, path prefixer and visibility converter from the adapter.
        $bucketName = \Closure::bind(fn () => $this->bucket, $this, parent::class)();

        $mimeTypeDetector = \Closure::bind(fn () => $this->mimeTypeDetector, $this, parent::class)();

        $prefixer = \Closure::bind(fn () => $this->prefixer, $this, parent::class)();

        /** @var PortableVisibilityConverter $visibilityConverter */
        $visibilityConverter = \Closure::bind(fn () => $this->visibility, $this, parent::class)();

        // Copy the files in batches. This is necessary to have open sockets and not run into the "Too many open files" error.
        foreach (array_chunk($files, $this->batchSize) as $filesBatch) {
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
                    /** @var 'private'|'public-read' $visibility */
                    $visibility = $visibilityConverter->visibilityToAcl($file->visibility);

                    $options = [
                        'Bucket' => $bucketName,
                        'Key' => $prefixer->prefixPath($targetFile),
                        'Body' => $sourceFile,
                        'ACL' => $visibility,
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
