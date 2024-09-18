<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Log\Package;
use Twig\Cache\FilesystemCache;

#[Package('core')]
class ConfigurableFilesystemCache extends FilesystemCache
{
    /**
     * @var string
     */
    protected $configHash = '';

    /**
     * @var string
     */
    protected $cacheDirectory;

    /**
     * @var string[]
     */
    protected array $templateScopes = [TemplateScopeDetector::DEFAULT_SCOPE];

    public function __construct(
        string $directory,
        int $options = 0
    ) {
        $this->cacheDirectory = rtrim($directory, '\/') . '/';
        parent::__construct($directory, $options);
    }

    public function generateKey(string $name, string $className): string
    {
        $hash = hash('xxh128', $className . $this->configHash . implode('', $this->templateScopes));

        return $this->cacheDirectory . $hash[0] . $hash[1] . '/' . $hash . '.php';
    }

    public function setConfigHash(string $configHash): void
    {
        $this->configHash = $configHash;
    }

    /**
     * @param string[] $templateScopes
     */
    public function setTemplateScopes(array $templateScopes): void
    {
        $this->templateScopes = $templateScopes;
    }
}
