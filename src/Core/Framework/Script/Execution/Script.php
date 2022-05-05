<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal only for use by the app-system
 */
class Script extends Struct
{
    protected string $name;

    protected string $script;

    protected array $twigOptions;

    protected array $includes = [];

    protected \DateTimeInterface $lastModified;

    private ?ScriptAppInformation $scriptAppInformation;

    private bool $active;

    public function __construct(string $name, string $script, \DateTimeInterface $lastModified, ?ScriptAppInformation $scriptAppInformation = null, array $twigOptions = [], array $includes = [], bool $active = true)
    {
        $this->name = $name;
        $this->script = $script;
        $this->twigOptions = $twigOptions;
        $this->lastModified = $lastModified;
        $this->includes = $includes;
        $this->scriptAppInformation = $scriptAppInformation;
        $this->active = $active;
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

    /**
     * @return Script[]
     */
    public function getIncludes(): array
    {
        return $this->includes;
    }

    public function isAppScript(): bool
    {
        return $this->scriptAppInformation !== null;
    }

    public function getScriptAppInformation(): ?ScriptAppInformation
    {
        return $this->scriptAppInformation;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
