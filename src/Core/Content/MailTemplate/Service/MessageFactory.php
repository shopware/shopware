<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessageFactory implements MessageFactoryInterface
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
     */
    public function createMessage(
        string $subject,
        array $sender,
        array $recipients,
        array $contents,
        array $attachments,
        ?array $binAttachments = null
    ): \Swift_Message {
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
}
