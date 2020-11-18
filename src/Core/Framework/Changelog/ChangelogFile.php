<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog;

use Shopware\Core\Framework\Struct\Struct;

class ChangelogFile extends Struct
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
     * @var ChangelogDefinition
     */
    protected $definition;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): ChangelogFile
    {
        $this->name = $name;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): ChangelogFile
    {
        $this->path = $path;

        return $this;
    }

    public function getDefinition(): ChangelogDefinition
    {
        return $this->definition;
    }

    public function setDefinition(ChangelogDefinition $definition): ChangelogFile
    {
        $this->definition = $definition;

        return $this;
    }
}
