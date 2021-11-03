<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal only for use by the app-system
 */
class ExecutableScript extends Struct
{
    protected string $name;

    protected string $script;

    protected array $twigOptions;

    protected \DateTimeInterface $lastModified;

    public function __construct(string $name, string $script, \DateTimeInterface $lastModified, array $twigOptions)
    {
        $this->name = $name;
        $this->script = $script;
        $this->twigOptions = $twigOptions;
        $this->lastModified = $lastModified;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getScript(): string
    {
        return $this->script;
    }

    public function getTwigOptions(): array
    {
        return $this->twigOptions;
    }

    public function getLastModified(): \DateTimeInterface
    {
        return $this->lastModified;
    }
}
