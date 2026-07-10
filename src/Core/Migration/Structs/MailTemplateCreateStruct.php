<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Structs;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
class MailTemplateCreateStruct
{
    protected string $enHtml;

    protected string $enPlain;

    protected string $deHtml;

    protected string $dePlain;

    public function __construct(
        protected string $mailTemplateFixtureDirectoryName,
        protected string $enSubject,
        protected string $deSubject,
        protected string $enDescription,
        protected string $deDescription,
        protected string $enSenderName,
        protected string $deSenderName,
        protected bool $isSystemDefault = true,
    ) {
        $filesystem = new Filesystem();
        $path = __DIR__ . '/../Fixtures/mails/' . $this->mailTemplateFixtureDirectoryName;

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

    public function getEnHtml(): string
    {
        return $this->enHtml;
    }

    public function getEnPlain(): string
    {
        return $this->enPlain;
    }

    public function getDeHtml(): string
    {
        return $this->deHtml;
    }

    public function getDePlain(): string
    {
        return $this->dePlain;
    }

    public function getEnSubject(): string
    {
        return $this->enSubject;
    }

    public function getDeSubject(): string
    {
        return $this->deSubject;
    }

    public function getEnDescription(): string
    {
        return $this->enDescription;
    }

    public function getDeDescription(): string
    {
        return $this->deDescription;
    }

    public function getEnSenderName(): string
    {
        return $this->enSenderName;
    }

    public function getDeSenderName(): string
    {
        return $this->deSenderName;
    }

    public function isSystemDefault(): bool
    {
        return $this->isSystemDefault;
    }
}
