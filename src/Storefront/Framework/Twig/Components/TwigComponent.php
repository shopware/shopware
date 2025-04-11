<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Components;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Filesystem\Path;
use Symfony\UX\TwigComponent\ComponentMetadata;

/**
 * @internal
 */
#[Package('framework')]
class TwigComponent extends Struct
{
    private const MAIN_NAMESPACE = 'Storefront';

    protected string $name;

    protected string $path;

    protected string $namespace;

    protected ?ComponentMetadata $metadata = null;

    public function __construct(
        string $name,
        string $path,
        string $namespace,
    ) {
        $this->name = $name;
        $this->path = $path;
        $this->namespace = $namespace;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
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

    public function getPath(): string
    {
        return $this->path;
    }

    public function getRelativeNamespacePath(): string
    {
        $relativeName = $this->getName();

        if ($this->namespace !== self::MAIN_NAMESPACE) {
            $relativeName = $this->namespace . ':' . $relativeName;
        }

        return str_replace(':', '/', $relativeName);
    }

    public function getRelativeNamespaceDirectory(): string
    {
        $relativeName = $this->getName();

        if ($this->namespace !== self::MAIN_NAMESPACE) {
            $relativeName = $this->namespace . ':' . $relativeName;
        }

        $nameParts = explode(':', $relativeName);

        array_pop($nameParts);

        return implode('/', $nameParts);
    }

    public function getStylePath(): ?string
    {
        $stylePath = Path::join($this->getDirectory(), $this->getBaseName() . '.scss');

        if (!is_file($stylePath)) {
            return null;
        }

        return $stylePath;
    }

    public function getScriptPath(): ?string
    {
        $scriptPath = Path::join($this->getDirectory(), $this->getBaseName() . '.js');

        if (!is_file($scriptPath)) {
            return null;
        }

        return $scriptPath;
    }

    public function isIndexComponent(): bool
    {
        return strcasecmp(basename($this->path), 'index.html.twig') === 0;
    }

    public function getDirectory(): string
    {
        return Path::getDirectory($this->path);
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function getMetadata(): ?ComponentMetadata
    {
        return $this->metadata;
    }

    public function setMetadata(ComponentMetadata $metadata): void
    {
        $this->metadata = $metadata;
    }
}
