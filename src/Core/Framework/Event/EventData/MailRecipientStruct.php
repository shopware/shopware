<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

class MailRecipientStruct
{
    /**
     * @var array
     */
    private $recipients;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.3.0 use $bccRecipients instead
     */
    private $bcc;

    /**
     * @var array|null
     */
    private $bccRecipients;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.3.0 use $ccRecipients instead
     */
    private $cc;

    /**
     * @var array|null
     */
    private $ccRecipients;

    /**
     * @param array $recipients ['email' => 'firstName lastName']
     * @param array|null $ccRecipients ['email' => 'firstName lastName']
     * @param array|null $bccRecipients ['email' => 'firstName lastName']
     */
    public function __construct(array $recipients, ?array $ccRecipients = null, ?array $bccRecipients = null)
    {
        $this->recipients = $recipients;
        $this->ccRecipients = $ccRecipients;
        $this->bccRecipients = $bccRecipients;
    }

    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function setRecipients(array $recipients): void
    {
        $this->recipients = $recipients;
    }

    /**
     * @deprecated tag:v6.3.0 use $getBccRecipients instead
     */
    public function getBcc(): ?string
    {
        return $this->bcc ?? $this->convertRecipientArrayToString($this->bccRecipients);
    }

    /**
     * @deprecated tag:v6.3.0 use $setBccRecipients instead
     */
    public function setBcc(?string $bcc): void
    {
        $this->bcc = $bcc ?? $this->convertRecipientArrayToString($this->bccRecipients);
    }

    public function getBccRecipients(): ?array
    {
        return $this->bccRecipients;
    }

    public function setBccRecipients(?array $bccRecipients): void
    {
        $this->bccRecipients = $bccRecipients;
    }

    /**
     * @deprecated tag:v6.3.0 use $getCcRecipients instead
     */
    public function getCc(): ?string
    {
        return $this->cc ?? $this->convertRecipientArrayToString($this->ccRecipients);
    }

    /**
     * @deprecated tag:v6.3.0 use $setCcRecipients instead
     */
    public function setCc(?string $cc): void
    {
        $this->cc = $cc ?? $this->convertRecipientArrayToString($this->ccRecipients);
    }

    public function getCcRecipients(): ?array
    {
        return $this->ccRecipients;
    }

    public function setCcRecipients(?array $ccRecipients): void
    {
        $this->ccRecipients = $ccRecipients;
    }

    /**
     * @deprecated tag:v6.3.0 Don't use strings for the recipients
     */
    private function convertRecipientArrayToString(?array $recipients): ?string
    {
        if (is_null($recipients) || $recipients === []) {
            return null;
        }

        $items = [];

        foreach ($recipients as $email => $name) {
            $items[] = sprintf('%s <%s>', trim($name), $email);
        }

        return implode(', ', $items);
    }
}
