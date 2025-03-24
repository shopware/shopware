<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class MailUpdate
{
    public function __construct(
        protected string $type,
        protected ?string $enPlain = null,
        protected ?string $enHtml = null,
        protected ?string $dePlain = null,
        protected ?string $deHtml = null
    ) {
    }

    public function getEnPlain(): ?string
    {
        return $this->enPlain;
    }

    public function setEnPlain(?string $enPlain): void
    {
        $this->enPlain = $enPlain;
    }

    public function getEnHtml(): ?string
    {
        return $this->enHtml;
    }

    public function setEnHtml(?string $enHtml): void
    {
        $this->enHtml = $enHtml;
    }

    public function getDePlain(): ?string
    {
        return $this->dePlain;
    }

    public function setDePlain(?string $dePlain): void
    {
        $this->dePlain = $dePlain;
    }

    public function getDeHtml(): ?string
    {
        return $this->deHtml;
    }

    public function setDeHtml(?string $deHtml): void
    {
        $this->deHtml = $deHtml;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
