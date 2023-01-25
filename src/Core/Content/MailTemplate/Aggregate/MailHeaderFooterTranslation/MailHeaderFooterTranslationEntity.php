<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation;

use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('sales-channel')]
class MailHeaderFooterTranslationEntity extends TranslationEntity
{
    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $headerHtml;

    /**
     * @var string|null
     */
    protected $headerPlain;

    /**
     * @var string|null
     */
    protected $footerHtml;

    /**
     * @var string|null
     */
    protected $footerPlain;

    /**
     * @var MailHeaderFooterEntity|null
     */
    protected $mailHeaderFooter;

    /**
     * @var string
     */
    protected $mailHeaderFooterId;

    public function getMailHeaderFooterId(): string
    {
        return $this->mailHeaderFooterId;
    }

    public function setMailHeaderFooterId(string $mailHeaderFooterId): void
    {
        $this->mailHeaderFooterId = $mailHeaderFooterId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getHeaderHtml(): ?string
    {
        return $this->headerHtml;
    }

    public function setHeaderHtml(?string $headerHtml): void
    {
        $this->headerHtml = $headerHtml;
    }

    public function getHeaderPlain(): ?string
    {
        return $this->headerPlain;
    }

    public function setHeaderPlain(?string $headerPlain): void
    {
        $this->headerPlain = $headerPlain;
    }

    public function getFooterHtml(): ?string
    {
        return $this->footerHtml;
    }

    public function setFooterHtml(?string $footerHtml): void
    {
        $this->footerHtml = $footerHtml;
    }

    public function getFooterPlain(): ?string
    {
        return $this->footerPlain;
    }

    public function setFooterPlain(?string $footerPlain): void
    {
        $this->footerPlain = $footerPlain;
    }

    public function getMailHeaderFooter(): ?MailHeaderFooterEntity
    {
        return $this->mailHeaderFooter;
    }

    public function setMailHeaderFooter(MailHeaderFooterEntity $mailHeaderFooter): void
    {
        $this->mailHeaderFooter = $mailHeaderFooter;
    }
}
