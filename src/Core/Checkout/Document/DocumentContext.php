<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Struct\Struct;

class DocumentContext extends Struct
{
    /**
     * @var bool
     */
    protected $displayPrices = true;

    /**
     * @var string|null
     */
    protected $logoPath;

    /**
     * @var bool
     */
    protected $displayFooter = true;

    /**
     * @var bool
     */
    protected $displayHeader = true;

    /**
     * @var bool
     */
    protected $displayLineItems = true;

    /**
     * @var bool
     */
    protected $displayLineItemPosition = true;

    /**
     * @var bool
     */
    protected $displayPageCount = true;

    /**
     * @var string|null
     */
    protected $title;

    public function shouldDisplayPrices(): bool
    {
        return $this->displayPrices;
    }

    public function setDisplayPrices(bool $displayPrices): DocumentContext
    {
        $this->displayPrices = $displayPrices;

        return $this;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(?string $logoPath): DocumentContext
    {
        $this->logoPath = $logoPath;

        return $this;
    }

    public function shouldDisplayFooter(): bool
    {
        return $this->displayFooter;
    }

    public function setDisplayFooter(bool $displayFooter): DocumentContext
    {
        $this->displayFooter = $displayFooter;

        return $this;
    }

    public function shouldDisplayHeader(): bool
    {
        return $this->displayHeader;
    }

    public function setDisplayHeader(bool $displayHeader): DocumentContext
    {
        $this->displayHeader = $displayHeader;

        return $this;
    }

    public function shouldDisplayLineItems(): bool
    {
        return $this->displayLineItems;
    }

    public function setDisplayLineItems(bool $displayLineItems): DocumentContext
    {
        $this->displayLineItems = $displayLineItems;

        return $this;
    }

    public function shouldDisplayLineItemPosition(): bool
    {
        return $this->displayLineItemPosition;
    }

    public function setDisplayLineItemPosition(bool $displayLineItemPosition): DocumentContext
    {
        $this->displayLineItemPosition = $displayLineItemPosition;

        return $this;
    }

    public function shouldDisplayPageCount(): bool
    {
        return $this->displayPageCount;
    }

    public function setDisplayPageCount(bool $displayPageCount): DocumentContext
    {
        $this->displayPageCount = $displayPageCount;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): DocumentContext
    {
        $this->title = $title;

        return $this;
    }
}
