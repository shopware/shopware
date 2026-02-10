<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('inventory')]
class AdminElasticsearchHelper
{
    public function __construct(
        private bool $adminEsEnabled,
        private readonly bool $refreshIndices,
        private readonly string $adminIndexPrefix,
        private readonly string $environment,
        private readonly bool $throwException,
        private readonly LoggerInterface $logger
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->adminEsEnabled;
    }

    /**
     * @deprecated tag:v6.8.0 - use \Shopware\Elasticsearch\Admin\AdminElasticsearchHelper::isEnabled instead
     */
    public function getEnabled(): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(
            __CLASS__,
            __METHOD__,
            'v6.8.0.0',
            'isEnabled'
        ));

        return $this->adminEsEnabled;
    }

    /**
     * Only used for unit tests because the container parameter bag is frozen and can not be changed at runtime.
     * Therefore this function can be used to test different behaviours
     *
     * @internal
     */
    public function setEnabled(bool $enabled): self
    {
        $this->adminEsEnabled = $enabled;

        return $this;
    }

    public function getRefreshIndices(): bool
    {
        return $this->refreshIndices;
    }

    public function getPrefix(): string
    {
        return $this->adminIndexPrefix;
    }

    public function getIndex(string $name): string
    {
        return $this->adminIndexPrefix . '-' . \strtolower(\str_replace(['_', ' '], '-', $name));
    }

    public function logAndThrowException(\Throwable $exception): void
    {
        $this->logger->critical($exception->getMessage());

        if ($this->environment === 'test' || $this->throwException) {
            throw $exception;
        }
    }
}
