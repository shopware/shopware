<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter;

use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

#[Package('sales-channel')]
class MailHeaderFooterEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var bool
     */
    protected $systemDefault;

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
     * @var SalesChannelCollection|null
     */
    protected $salesChannels;

    /**
     * @var MailHeaderFooterTranslationCollection|null
     */
    protected $translations;

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

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }

    public function getTranslations(): ?MailHeaderFooterTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(MailHeaderFooterTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getSystemDefault(): bool
    {
        return $this->systemDefault;
    }

    public function setSystemDefault(bool $systemDefault): void
    {
        $this->systemDefault = $systemDefault;
    }
}
