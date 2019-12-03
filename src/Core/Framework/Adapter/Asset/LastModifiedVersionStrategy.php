<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * Enables cache busting depending on last modified timestamp.
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
     * {@inheritdoc}
     */
    public function getVersion($path)
    {
        return $this->applyVersion($path);
    }

    /**
     * Reads the last modified date of the file and
     * add it to the file path as query string parameter
     *
     * @return string
     */
    public function applyVersion($path)
    {
        $localFile = $this->bundlePath . '/Resources/public/' . $path;

        if (!file_exists($localFile)) {
            return $path;
        }

        return $path . '?' . filemtime($localFile) . filesize($localFile);
    }
}
