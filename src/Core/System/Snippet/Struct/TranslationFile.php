<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
readonly class TranslationFile
{
    public function __construct(
        public string $filename,
        public string $path,
        public string $domain,
        public string $locale,
        public string $language,
        public ?string $script = null,
        public ?string $region = null,
        public bool $isBase = false,
    ) {
    }

    public function getAgnosticFilename(): string
    {
        return \sprintf(
            '%s%s%s.json',
            $this->domain !== 'administration' ? $this->domain . '.' : '',
            $this->language,
            $this->isBase ? '.base' : '',
        );
    }

    public function getAgnosticPath(): string
    {
        return \sprintf(
            '%s/%s',
            $this->path,
            $this->getAgnosticFilename(),
        );
    }

    public function getFullPath(): string
    {
        return \sprintf('%s/%s', $this->path, $this->filename);
    }
}
