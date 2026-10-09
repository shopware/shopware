<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class SalesChannelFileAdministrationDetail
{
    /**
     * @param list<SalesChannelFileAdministrationTemplate> $templates
     */
    public function __construct(
        public string $fileFamily,
        public string $fileName,
        public string $templatePath,
        public string $contentType,
        public array $templates,
        public bool $supportsUserProvidedContent,
        public ?SalesChannelFileAdministrationConfiguration $configuration,
    ) {
    }
}
