<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class Script extends Struct
{
    /**
     * @param array<string, mixed> $twigOptions
     * @param array<Script> $includes
     */
    public function __construct(
        protected string $name,
        protected string $script,
        protected \DateTimeInterface $lastModified,
        private readonly ?ScriptAppInformation $scriptAppInformation = null,
        protected array $twigOptions = [],
        protected array $includes = [],
        private readonly bool $active = true
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getScript(): string
    {
        return $this->script;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTwigOptions(): array
    {
        return $this->twigOptions;
    }

    public function getLastModified(): \DateTimeInterface
    {
        return $this->lastModified;
    }

    /**
     * @return array<Script>
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
