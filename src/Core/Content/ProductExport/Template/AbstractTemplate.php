<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Template;

abstract class AbstractTemplate implements TemplateInterface
{
    protected $name;

    protected $translationKey;

    protected $headerTemplate;

    protected $bodyTemplate;

    protected $footerTemplate;

    protected $fileName;

    protected $encoding;

    protected $fileFormat;

    protected $generateByCronjob;

    protected $interval;

    public function getName(): string
    {
        return $this->name;
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    public function getHeaderTemplate(): string
    {
        return $this->headerTemplate;
    }

    public function getBodyTemplate(): string
    {
        return $this->bodyTemplate;
    }

    public function getFooterTemplate(): string
    {
        return $this->footerTemplate;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getEncoding(): string
    {
        return $this->encoding;
    }

    public function getFileFormat(): string
    {
        return $this->fileFormat;
    }

    public function getGenerateByCronjob(): bool
    {
        return $this->generateByCronjob;
    }

    public function getInterval(): int
    {
        return $this->interval;
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
