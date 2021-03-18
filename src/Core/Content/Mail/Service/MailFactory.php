<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
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
