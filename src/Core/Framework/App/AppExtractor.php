<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\App\Exception\AppArchiveValidationFailure;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\Util\ZipUtils;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('framework')]
class AppExtractor
{
    public function __construct(
        private readonly AppArchiveValidator $appArchiveValidator,
        private readonly Filesystem $filesystem = new Filesystem()
    ) {
    }

    /**
     * If we know the expected app name we can perform extra validation.
     *
     * @throws PluginException
     * @throws AppArchiveValidationFailure
     *
     * @return Manifest The manifest of the extracted app, its path points to the extracted app directory
     *
     **/
    public function extract(string $zipLocation, string $destinationDirectory, ?string $appName = null): Manifest
    {
        $this->filesystem->mkdir($destinationDirectory);

        $archive = ZipUtils::openZip($zipLocation);

        try {
            $this->appArchiveValidator->validate($archive, $appName);
            $appName = $this->getAppName($archive);
            if (!$archive->extractTo($destinationDirectory)) {
                throw AppException::invalidArgument('Could not extract app archive');
            }
        } finally {
            $archive->close();
        }

        return Manifest::createFromXmlFile(Path::join($destinationDirectory, $appName, 'manifest.xml'));
    }

    private function getAppName(\ZipArchive $archive): string
    {
        return explode('/', $archive->statIndex(0)['name'] ?? '')[0];
    }
}
