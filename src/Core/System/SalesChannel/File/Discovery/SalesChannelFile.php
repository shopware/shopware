<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Discovery;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore Simple value object without behavior.
 */
#[Package('framework')]
final readonly class SalesChannelFile
{
    public const TEMPLATE_ROOT = 'files';

    public const DEFAULT_FILE_FAMILY = 'agentic';

    public const TEMPLATE_SUFFIX = '.twig';

    /**
     * @param array<string, string> $templates Twig namespace mapped to resolved template name
     */
    public function __construct(
        public string $fileFamily,
        public string $fileName,
        public string $templatePath,
        public string $contentType,
        public string $baseTemplateName,
        public array $templates,
    ) {
    }
}
