<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Twig\Cache\FilesystemCache;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-type TwigOptions array{cache?: FilesystemCache, debug?: bool, auto_reload?: bool}
 */
#[Package('framework')]
class Script extends Struct
{
    /**
     * @var TwigOptions
     */
    private array $twigOptions = [];

    /**
     * @param array<Script> $includes
     */
    public function __construct(
        protected string $name,
        protected string $script,
        protected \DateTimeInterface $lastModified,
        private readonly ?ScriptAppInformation $scriptAppInformation = null,
        protected array $includes = [],
        private readonly bool $active = true,
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
     * @return TwigOptions
     */
    public function getTwigOptions(): array
    {
        return $this->twigOptions;
    }

    /**
     * @param TwigOptions $twigOptions
     */
    public function setTwigOptions(array $twigOptions): void
    {
        $this->twigOptions = $twigOptions;
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
