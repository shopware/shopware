<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Struct;

use Composer\Package\RootPackageInterface;
use Shopware\Core\Framework\Struct\Struct;

class PluginFromFileSystemStruct extends Struct
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var bool
     */
    protected $managedByComposer;

    /**
     * @var RootPackageInterface
     */
    protected $composerPackage;

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getManagedByComposer(): bool
    {
        return $this->managedByComposer;
    }

    public function getComposerPackage(): RootPackageInterface
    {
        return $this->composerPackage;
    }
}
