<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Event\Hooks;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * Triggered once per document generation, after the order is loaded and the document number is allocated, but before any renderer runs.
 *
 * @experimental stableVersion:v6.8.0 feature:DOCUMENT_GENERATION_REWORK
 *
 * @hook-use-case data_loading
 *
 * @codeCoverageIgnore
 *
 * @since 6.7.14.0
 */
#[Package('after-sales')]
final class DocumentGenerationHook extends Hook
{
    final public const HOOK_NAME = 'document-generation';

    /**
     * @param list<string> $formats
     *
     * @internal
     */
    public function __construct(
        private readonly string $orderId,
        private readonly string $orderVersionId,
        private readonly string $salesChannelId,
        private readonly string $documentType,
        private readonly string $documentNumber,
        private readonly array $formats,
        Context $context,
    ) {
        parent::__construct($context);
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    /**
     * @return list<string>
     */
    public function getFormats(): array
    {
        return $this->formats;
    }

    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
        ];
    }
}
