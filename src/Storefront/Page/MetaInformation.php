<?php declare(strict_types=1);

namespace Shopware\Storefront\Page;

use Shopware\Core\Framework\Struct\Struct;

class MetaInformation extends Struct
{
    /**
     * @var string
     */
    protected $metaTitle = '';

    /**
     * @var string
     */
    protected $metaDescription = '';

    /**
     * @var string
     */
    protected $metaKeywords = '';

    /**
     * @var string
     */
    protected $author = '';

    /**
     * @var string
     */
    protected $xmlLang = '';

    /**
     * @var string
     */
    protected $copyrightYear = '';

    /**
     * @var string
     */
    protected $revisit = '';

    /**
     * @var string
     */
    protected $robots = '';

    public function getMetaTitle(): string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getMetaDescription(): string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function getMetaKeywords(): string
    {
        return $this->metaKeywords;
    }

    public function setMetaKeywords(string $metaKeywords): void
    {
        $this->metaKeywords = $metaKeywords;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): void
    {
        $this->author = $author;
    }

    public function getXmlLang(): string
    {
        return $this->xmlLang;
    }

    public function setXmlLang(string $xmlLang): void
    {
        $this->xmlLang = $xmlLang;
    }

    public function getCopyrightYear(): string
    {
        return $this->copyrightYear;
    }

    public function setCopyrightYear(string $copyrightYear): void
    {
        $this->copyrightYear = $copyrightYear;
    }

    public function getRevisit(): string
    {
        return $this->revisit;
    }

    public function setRevisit(string $revisit): void
    {
        $this->revisit = $revisit;
    }

    public function getRobots(): string
    {
        return $this->robots;
    }

    public function setRobots(string $robots): void
    {
        $this->robots = $robots;
    }
}
