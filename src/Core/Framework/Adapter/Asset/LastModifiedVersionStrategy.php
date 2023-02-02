<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Shopware\Core\Framework\Feature;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * Enables cache busting depending on last modified timestamp.
 *
 * @deprecated tag:v6.5.0 - Use FlysystemLastModifiedVersionStrategy instead
 */
class LastModifiedVersionStrategy implements VersionStrategyInterface
{
    /**
     * @var string
     */
    private $bundlePath;

    public function __construct(string $bundlePath)
    {
        $this->bundlePath = $bundlePath;
    }

    /**
     * @return string
     */
    public function getVersion(string $path)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'FlysystemLastModifiedVersionStrategy')
        );

        return $this->applyVersion($path);
    }

    /**
     * Reads the last modified date of the file and
     * add it to the file path as query string parameter
     *
     * @return string
     */
    public function applyVersion(string $path)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'FlysystemLastModifiedVersionStrategy')
        );

        $localFile = $this->bundlePath . '/Resources/public/' . $path;

        if (!file_exists($localFile)) {
            return $path;
        }

        return $path . '?' . filemtime($localFile) . filesize($localFile);
    }
}
