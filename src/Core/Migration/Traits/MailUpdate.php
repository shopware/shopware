<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

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

    public function loadByDirectoryName(string $directoryName): void
    {
        $filesystem = new Filesystem();
        $path = __DIR__ . '/../Fixtures/mails/' . $directoryName;

        $this->enHtml = $filesystem->readFile($path . '/en-html.html.twig');
        $this->deHtml = $filesystem->readFile($path . '/de-html.html.twig');

        if ($filesystem->exists($path . '/en-plain.txt.twig')) {
            $this->enPlain = $filesystem->readFile($path . '/en-plain.txt.twig');
        } else {
            $this->enPlain = $filesystem->readFile($path . '/en-plain.html.twig');
        }

        if ($filesystem->exists($path . '/de-plain.txt.twig')) {
            $this->dePlain = $filesystem->readFile($path . '/de-plain.txt.twig');
        } else {
            $this->dePlain = $filesystem->readFile($path . '/de-plain.html.twig');
        }
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
