<?php declare(strict_types=1);

namespace Shopware\Tests\Migration;

/**
 * @internal
 */
class Translations
{
    private ?string $enPlain = null;

    private ?string $enHtml = null;

    private ?string $dePlain = null;

    private ?string $deHtml = null;

    public function getEnPlain(): ?string
    {
        return $this->enPlain;
    }

    public function getEnHtml(): ?string
    {
        return $this->enHtml;
    }

    public function getDePlain(): ?string
    {
        return $this->dePlain;
    }

    public function getDeHtml(): ?string
    {
        return $this->deHtml;
    }

    public function setEnPlain(string $enPlain): void
    {
        $this->enPlain = $enPlain;
    }

    public function setEnHtml(string $enHtml): void
    {
        $this->enHtml = $enHtml;
    }

    public function setDePlain(string $dePlain): void
    {
        $this->dePlain = $dePlain;
    }

    public function setDeHtml(string $deHtml): void
    {
        $this->deHtml = $deHtml;
    }
}
