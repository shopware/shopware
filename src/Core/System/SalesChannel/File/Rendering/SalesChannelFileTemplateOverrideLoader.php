<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Rendering;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileTemplateOverrideLoader implements LoaderInterface, ResetInterface
{
    /**
     * @var list<array<string, string>>
     */
    private array $templateOverrides = [];

    /**
     * @template T
     *
     * @param array<string, string> $templateOverrides
     * @param \Closure(): T $callback
     *
     * @return T
     */
    public function withTemplateOverrides(array $templateOverrides, \Closure $callback): mixed
    {
        $this->templateOverrides[] = $templateOverrides;

        try {
            return $callback();
        } finally {
            array_pop($this->templateOverrides);
        }
    }

    public function getSourceContext(string $name): Source
    {
        $template = $this->getTemplateOverride($name);

        if ($template === null) {
            throw new LoaderError(\sprintf('Template "%s" is not defined.', $name));
        }

        return new Source($template, $name);
    }

    public function getCacheKey(string $name): string
    {
        $template = $this->getTemplateOverride($name);

        if ($template === null) {
            throw new LoaderError(\sprintf('Template "%s" is not defined.', $name));
        }

        return 'sales-channel-file-template-override:' . $name . ':' . Hasher::hash($template);
    }

    public function isFresh(string $name, int $time): bool
    {
        return $this->getTemplateOverride($name) !== null;
    }

    public function exists(string $name): bool
    {
        return $this->getTemplateOverride($name) !== null;
    }

    public function reset(): void
    {
        $this->templateOverrides = [];
    }

    private function getTemplateOverride(string $name): ?string
    {
        for ($index = \count($this->templateOverrides) - 1; $index >= 0; --$index) {
            if (\array_key_exists($name, $this->templateOverrides[$index])) {
                return $this->templateOverrides[$index][$name];
            }
        }

        return null;
    }
}
