<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Composer\IO\NullIO;
use GuzzleHttp\Client;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\NoPluginFoundInZipException;
use Shopware\Core\Framework\Plugin\Util\ZipUtils;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class PluginManagementService
{
    /**
     * @var string
     */
    private $pluginPath;

    /**
     * @var PluginZipDetector
     */
    private $pluginZipDetector;

    /**
     * @var PluginExtractor
     */
    private $pluginExtractor;

    /**
     * @var PluginService
     */
    private $pluginService;

    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(
        string $pluginPath,
        PluginZipDetector $pluginZipDetector,
        PluginExtractor $pluginExtractor,
        PluginService $pluginService,
        Filesystem $filesystem
    ) {
        $this->pluginPath = $pluginPath;
        $this->pluginZipDetector = $pluginZipDetector;
        $this->pluginExtractor = $pluginExtractor;
        $this->pluginService = $pluginService;
        $this->filesystem = $filesystem;
    }

    public function extractPluginZip(string $file): void
    {
        $archive = ZipUtils::openZip($file);

        if ($this->pluginZipDetector->isPlugin($archive)) {
            $this->pluginExtractor->extract($archive);
        } else {
            throw new NoPluginFoundInZipException($file);
        }
    }

    public function uploadPlugin(UploadedFile $file): void
    {
        $tempFileName = tempnam(sys_get_temp_dir(), $file->getClientOriginalName());
        $tempDirectory = dirname(realpath($tempFileName));

        $file = $file->move($tempDirectory, $tempFileName);

        $this->extractPluginZip($file->getPathname());
    }

    public function downloadStorePlugin(string $location, Context $context): int
    {
        $tempFileName = tempnam(sys_get_temp_dir(), 'store-plugin');

        $client = new Client();
        $response = $client->request('GET', $location, ['sink' => $tempFileName]);
        $statusCode = $response->getStatusCode();

        if ($statusCode !== Response::HTTP_OK) {
            return $statusCode;
        }
        $this->extractPluginZip($tempFileName);

        $this->pluginService->refreshPlugins($context, new NullIO());

        return $statusCode;
    }

    public function deletePlugin(PluginEntity $plugin): void
    {
        $path = $this->pluginPath . DIRECTORY_SEPARATOR . $plugin->getName();
        $this->filesystem->remove($path);
    }
}
