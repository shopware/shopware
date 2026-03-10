<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

/**
 * @internal
 */
#[Package('TODO')]
class DocumentConfig
{
    /**
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        public readonly string $filePrefix,
        public readonly string $fileSuffix,
        // public readonly CompanyEntity $company,
        public array $extensions = [],
    ) {
    }
}
