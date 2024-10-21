<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class ManifestFactory
{
    public function createFromXmlFile(string $file): Manifest
    {
        return Manifest::createFromXmlFile($file);
    }
}
