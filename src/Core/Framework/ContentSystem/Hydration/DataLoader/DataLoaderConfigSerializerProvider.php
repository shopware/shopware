<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DataLoaderConfigSerializerProvider
{
    /**
     * @param ServiceLocator<AbstractContentDataLoaderConfigSerializer> $locator
     */
    public function __construct(
        private readonly ServiceLocator $locator
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function decode(string $source, array $data, ?PlaceholderValues $values = null): AbstractContentDataLoaderConfig
    {
        if (!$this->locator->has($source)) {
            throw ContentSystemException::configSerializerNotRegistered($source);
        }

        if ($values !== null) {
            $data = $this->replacePlaceholders($data, $values);
        }

        try {
            return $this->locator->get($source)->decode($data);
        } catch (HttpException $e) {
            if ($e instanceof ContentSystemException) {
                throw $e;
            }

            // A domain config serializer's decode() throws its own domain exception (a sibling of
            // ContentSystemException — DomainExceptionRule forbids a domain-namespaced class from
            // throwing ContentSystemException directly) for a client config-shape defect. Re-classify
            // it here at the shared decode seam so the single ContentSystemException client-defect
            // guard the binding / diagnostics stack already relies on catches it, instead of the
            // domain sibling escaping as an uncaught 500.
            throw ContentSystemException::invalidLoaderConfig($source, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function encode(string $source, AbstractContentDataLoaderConfig $config): array
    {
        if (!$this->locator->has($source)) {
            throw ContentSystemException::configSerializerNotRegistered($source);
        }

        try {
            return $this->locator->get($source)->encode($config);
        } catch (HttpException $e) {
            if ($e instanceof ContentSystemException) {
                throw $e;
            }

            // Mirror decode(): a domain serializer's encode() throws its own domain HttpException (a sibling
            // of ContentSystemException — DomainExceptionRule forbids throwing ContentSystemException directly).
            // Re-classify it here so the single client-defect guard the reconciler relies on catches it, rather
            // than the sibling escaping as an uncaught 500 on every content_layout write.
            throw ContentSystemException::invalidLoaderConfig($source, $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function replacePlaceholders(array $data, PlaceholderValues $values): array
    {
        foreach ($data as $key => $value) {
            if (\is_string($value)) {
                foreach ($values->all() as $name => $replacement) {
                    $value = \str_replace('{{' . $name . '}}', (string) $replacement, $value);
                }
                $data[$key] = $value;
            } elseif (\is_array($value)) {
                $data[$key] = $this->replacePlaceholders($value, $values);
            }
        }

        return $data;
    }
}
