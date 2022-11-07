<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

/**
 * @internal - may be changed in the future
 */
class CompilerConfiguration extends AbstractCompilerConfiguration
{
    /**
     * @var array<string, mixed>
     */
    private array $configuration;

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @return mixed
     */
    public function getValue(string $key)
    {
        return $this->configuration[$key] ?? null;
    }
}
