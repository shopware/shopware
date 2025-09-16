<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationFile extends Struct
{
    public function __construct(
        protected string $filename,
        protected string $path,
        protected string $domain,
        protected string $locale,
        protected string $language,
        protected ?string $script = null,
        protected ?string $region = null,
        protected bool $isBase = false,
    ) {
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    public function getScript(): ?string
    {
        return $this->script;
    }

    public function setScript(?string $script): void
    {
        $this->script = $script;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): void
    {
        $this->region = $region;
    }

    public function isBase(): bool
    {
        return $this->isBase;
    }

    public function setIsBase(bool $isBase): void
    {
        $this->isBase = $isBase;
    }

    public function getAgnosticFilename(): string
    {
        return \sprintf(
            '%s%s%s.json',
            $this->getDomain() !== 'administration' ? $this->getDomain() . '.' : '',
            $this->getLanguage(),
            $this->isBase ? '.base' : '',
        );
    }

    public function getAgnosticPath(): string
    {
        return \sprintf(
            '%s/%s',
            $this->getPath(),
            $this->getAgnosticFilename(),
        );
    }

    public function getFullPath(): string
    {
        return \sprintf('%s/%s', $this->getPath(), $this->getFilename());
    }
}
