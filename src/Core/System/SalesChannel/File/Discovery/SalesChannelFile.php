<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Discovery;

use Shopware\Core\Framework\Log\Package;

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
        private string $fileFamily,
        private string $fileName,
        private string $templatePath,
        private string $contentType,
        private string $baseTemplateName,
        private array $templates,
    ) {
    }

    public function getFileFamily(): string
    {
        return $this->fileFamily;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getBaseTemplateName(): string
    {
        return $this->baseTemplateName;
    }

    /**
     * @return array<string, string> Twig namespace mapped to resolved template name
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }
}
