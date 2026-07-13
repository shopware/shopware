<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ManifestFactory
{
    public function __construct(private readonly SourceResolver $sourceResolver)
    {
    }

    public function createFromXmlFile(string $file): Manifest
    {
        return Manifest::createFromXmlFile($file);
    }

    public function createFromApp(AppEntity $app): Manifest
    {
        $filesystem = $this->sourceResolver->filesystemForApp($app);

        return $this->createFromXmlFile($filesystem->path('manifest.xml'));
    }
}
