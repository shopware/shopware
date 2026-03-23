<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Components;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('framework')]
class TwigComponent
{
    private const MAIN_NAMESPACE = 'Storefront';

    public function __construct(
        public string $name,
        public string $path,
        public string $namespace,
    ) {
    }

    public function getBaseName(): string
    {
        $nameParts = explode(':', $this->name);

        if (\count($nameParts) <= 1) {
            return $this->name;
        }

        return $nameParts[\count($nameParts) - 1];
    }

    public function getTag(): string
    {
        $name = $this->name;

        if ($this->isIndexComponent()) {
            $name = str_replace(':index', '', $name);
        }

        if ($this->namespace !== self::MAIN_NAMESPACE) {
            return $this->namespace . ':' . $name;
        }

        return $name;
    }

    public function getRelativeNamespacePath(): string
    {
        $relativeName = $this->name;

        if ($this->namespace !== self::MAIN_NAMESPACE) {
            $relativeName = $this->namespace . ':' . $relativeName;
        }

        return str_replace(':', \DIRECTORY_SEPARATOR, $relativeName);
    }

    public function getRelativeNamespaceDirectory(): string
    {
        $relativeName = $this->name;

        if ($this->namespace !== self::MAIN_NAMESPACE) {
            $relativeName = $this->namespace . ':' . $relativeName;
        }

        $nameParts = explode(':', $relativeName);

        array_pop($nameParts);

        return implode('/', $nameParts);
    }

    public function getStylePath(): string
    {
        return Path::join($this->getDirectory(), $this->getBaseName() . '.scss');
    }

    public function getScriptPath(): string
    {
        return Path::join($this->getDirectory(), $this->getBaseName() . '.js');
    }

    public function isIndexComponent(): bool
    {
        return strcasecmp(basename($this->path), 'index.html.twig') === 0;
    }

    public function getDirectory(): string
    {
        return Path::getDirectory($this->path);
    }
}
