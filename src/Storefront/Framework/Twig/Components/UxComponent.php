<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Components;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Path;

#[Package('framework')]
class UxComponent extends Struct
{
    private const MAIN_NAMESPACE = 'Storefront';

    protected string $name;
    protected string $baseName;
    protected string $tag;
    protected string $path; 
    protected string $stylePath;

    protected string $scriptPath;
    protected string $directory;
    protected string $namespace;

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

    public function getBaseName(): string
    {
        $nameParts = explode(':', $this->name);

        return $nameParts[0];
    }

    public function getTag(): string
    {
        if ($this->namespace !== self::MAIN_NAMESPACE) {
            return $this->namespace . ':' . $this->name;
        }

        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getStylePath(): string
    {
        return Path::join($this->getDirectory(), $this->getBaseName() . '.scss');
    }

    public function getScriptPath(): string
    {
        return Path::join($this->getDirectory(), $this->getBaseName() . '.js');
    }

    public function getDirectory(): string
    {
        return Path::getDirectory($this->path);
    }

    public function getNamespace(): string
    {
        return $this->namespace;
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
}
