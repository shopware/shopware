<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Event\Hooks;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * Triggered once per document generation, after the order is loaded and the document number is
 * allocated, but before any renderer runs.
 *
 * On the `generate()` path, a script that throws aborts generation after the order version was
 * created and the document number was already consumed from its number range, leaving a gap in
 * the invoice sequence.
 *
 * @hook-use-case data_loading
 *
 * @since 6.7.13.0
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
        private readonly OrderEntity $order,
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

    public function getOrder(): OrderEntity
    {
        return $this->order;
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
