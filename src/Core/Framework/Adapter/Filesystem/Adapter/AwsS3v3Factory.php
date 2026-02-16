<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\AsyncAwsS3\PortableVisibilityConverter;
use League\Flysystem\FilesystemAdapter;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class AwsS3v3Factory implements AdapterFactoryInterface
{
    /**
     * @internal
     *
     * @param int<1, max> $batchWriteSize
     */
    public function __construct(
        private readonly int $batchWriteSize = 250
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public function create(array $config): FilesystemAdapter
    {
        $this->validateDependencies();

        $result = S3ClientFactory::create($config);

        $adapter = new AsyncAwsS3WriteBatchAdapter($result['client'], $result['bucket'], $result['root'], new PortableVisibilityConverter());
        $adapter->batchSize = $this->batchWriteSize;

        return $adapter;
    }

    public function getType(): string
    {
        return 'amazon-s3';
    }

    private function validateDependencies(): void
    {
        if (!class_exists(AsyncAwsS3Adapter::class)) {
            throw AdapterException::missingDependency('league/flysystem-async-aws-s3');
        }
    }
}
