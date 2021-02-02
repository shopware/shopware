<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\Exception\FeatureActiveException;
use Shopware\Core\Framework\Feature\Exception\FeatureNotActiveException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Symfony\Component\Mime\Email;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MailFactory extends AbstractMailFactory
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    public function __construct(ValidatorInterface $validator, FilesystemInterface $filesystem)
    {
        $this->validator = $validator;
        $this->filesystem = $filesystem;
    }

    /**
     * @param array $sender     e.g. ['shopware@example.com' => 'Shopware AG', 'symfony@example.com' => 'Symfony']
     * @param array $recipients e.g. ['shopware@example.com' => 'Shopware AG', 'symfony@example.com' => 'Symfony']
     * @param array $contents   e.g. ['text/plain' => 'Foo', 'text/html' => '<h1>Bar</h1>']
     *
     * @deprecated tag:v6.4.0 (flag:FEATURE_NEXT_12246) method will be removed. Use createMail instead.
     */
    public function createMessage(
        string $subject,
        array $sender,
        array $recipients,
        array $contents,
        array $attachments,
        ?array $binAttachments = null
    ): \Swift_Message {
        if (Feature::isActive('FEATURE_NEXT_12246')) {
            throw new FeatureActiveException('FEATURE_NEXT_12246');
        }

        $this->assertValidAddresses(array_keys($recipients));

        $message = (new \Swift_Message($subject))
            ->setFrom($sender)
            ->setTo($recipients);

        foreach ($contents as $contentType => $data) {
            $message->addPart($data, $contentType);
        }

        foreach ($attachments as $url) {
            $attachment = new \Swift_Attachment(
                $this->filesystem->read($url),
                basename($url),
                $this->filesystem->getMimetype($url)
            );
            $message->attach($attachment);
        }

        if (isset($binAttachments)) {
            foreach ($binAttachments as $binAttachment) {
                $attachment = new \Swift_Attachment(
                    $binAttachment['content'],
                    $binAttachment['fileName'],
                    $binAttachment['mimeType']
                );
                $message->attach($attachment);
            }
        }

        return $message;
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
        if (!Feature::isActive('FEATURE_NEXT_12246')) {
            throw new FeatureNotActiveException('FEATURE_NEXT_12246');
        }

        $this->assertValidAddresses(array_keys($recipients));

        $mail = (new Email())
            ->subject($subject)
            ->from(...$this->formatMailAddresses($sender))
            ->to(...$this->formatMailAddresses($recipients));

        foreach ($contents as $contentType => $data) {
            if ($contentType === 'text/html') {
                $mail->html($data);
            } else {
                $mail->text($data);
            }
        }

        foreach ($attachments as $url) {
            $mail->embed($this->filesystem->read($url) ?: '', basename($url), $this->filesystem->getMimetype($url) ?: null);
        }

        if (isset($binAttachments)) {
            foreach ($binAttachments as $binAttachment) {
                $mail->embed(
                    $binAttachment['content'],
                    $binAttachment['fileName'],
                    $binAttachment['mimeType']
                );
            }
        }

        foreach ($additionalData as $key => $value) {
            switch ($key) {
                case 'recipientsCc':
                    $mail->addCc(...$this->formatMailAddresses([$value => $value]));

                    break;
                case 'recipientsBcc':
                    $mail->addBcc(...$this->formatMailAddresses([$value => $value]));

                    break;
                case 'replyTo':
                    $mail->addReplyTo(...$this->formatMailAddresses([$value => $value]));

                    break;
                case 'returnPath':
                    $mail->returnPath(...$this->formatMailAddresses([$value => $value]));
            }
        }

        return $mail;
    }

    public function getDecorated(): AbstractMailFactory
    {
        throw new DecorationPatternException(self::class);
    }

    /**
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

    private function formatMailAddresses(array $addresses): array
    {
        $formattedAddresses = [];
        foreach ($addresses as $mail => $name) {
            $formattedAddresses[] = $name . ' <' . $mail . '>';
        }

        return $formattedAddresses;
    }
}
