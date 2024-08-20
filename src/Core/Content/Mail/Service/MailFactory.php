<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Package('services-settings')]
class MailFactory extends AbstractMailFactory
{
    /**
     * @internal
     */
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    public function create(
        string $subject,
        array $sender,
        array $recipients,
        array $contents,
        array $attachments,
        array $additionalData,
        ?array $binAttachments = null
    ): Email {
        $this->assertValidAddresses(array_keys($recipients));

        $mail = (new Mail())
            ->subject($subject)
            ->from(...$this->formatMailAddresses($sender))
            ->to(...$this->formatMailAddresses($recipients))
            ->setMailAttachmentsConfig($additionalData['attachmentsConfig'] ?? null);

        foreach ($contents as $contentType => $data) {
            if ($contentType === 'text/html') {
                $mail->html($data);
            } else {
                $mail->text($data);
            }
        }

        foreach ($attachments as $url) {
            $mail->addAttachmentUrl($url);
        }

        if (\is_array($binAttachments)) {
            foreach ($binAttachments as $binAttachment) {
                $mail->attach(
                    $binAttachment['content'],
                    $binAttachment['fileName'],
                    $binAttachment['mimeType']
                );
            }
        }

        foreach ($additionalData as $key => $value) {
            if (!\is_array($value) && !\is_string($value)) {
                continue;
            }
            if (!\in_array($key, ['recipientsCc', 'recipientsBcc', 'replyTo', 'returnPath'], true)) {
                continue;
            }
            $mailAddresses = \is_array($value) ? $value : [$value => $value];
            $this->assertValidAddresses(array_keys($mailAddresses));
            match ($key) {
                'recipientsCc' => $mail->addCc(...$this->formatMailAddresses($mailAddresses)),
                'recipientsBcc' => $mail->addBcc(...$this->formatMailAddresses($mailAddresses)),
                'replyTo' => $mail->addReplyTo(...$this->formatMailAddresses($mailAddresses)),
                'returnPath' => $mail->returnPath(...$this->formatMailAddresses($mailAddresses)),
            };
        }

        return $mail;
    }

    public function getDecorated(): AbstractMailFactory
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @param list<string> $addresses
     *
     * @throws ConstraintViolationException
     */
    private function assertValidAddresses(array $addresses): void
    {
        $constraints = (new ConstraintBuilder())
            ->isNotBlank()
            ->isEmail()
            ->getConstraints();

        $violations = new ConstraintViolationList();
        foreach ($addresses as $address) {
            $violations->addAll($this->validator->validate($address, $constraints));
        }

        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations, $addresses);
        }
    }

    /**
     * @param array<string, string|null> $addresses
     *
     * @return list<Address>
     */
    private function formatMailAddresses(array $addresses): array
    {
        $formattedAddresses = [];
        foreach ($addresses as $mail => $name) {
            $formattedAddresses[] = new Address($mail, $name ?? '');
        }

        return $formattedAddresses;
    }
}
