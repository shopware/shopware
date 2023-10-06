<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class MailRecipientStruct
{
    private ?string $bcc = null;

    private ?string $cc = null;

    /**
     * @param array $recipients ['email' => 'firstName lastName']
     */
    public function __construct(private array $recipients)
    {
    }

    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function setRecipients(array $recipients): void
    {
        $this->recipients = $recipients;
    }

    public function getBcc(): ?string
    {
        return $this->bcc;
    }

    public function setBcc(?string $bcc): void
    {
        $this->bcc = $bcc;
    }

    public function getCc(): ?string
    {
        return $this->cc;
    }

    public function setCc(?string $cc): void
    {
        $this->cc = $cc;
    }
}
