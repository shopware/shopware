<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\MailTemplate\Exception\MailAddressValidationException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessageFactory
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @param array $sender     e.g. ['shopware@example.com' => 'Shopware AG', 'symfony@example.com' => 'Symfony']
     * @param array $recipients e.g. ['shopware@example.com' => 'Shopware AG', 'symfony@example.com' => 'Symfony']
     * @param array $contents   e.g. ['text/plain' => 'Foo', 'text/html' => '<h1>Bar</h1>']
     *
     * @throws MailAddressValidationException
     */
    public function createMessage(string $subject, array $sender, array $recipients, array $contents): \Swift_Message
    {
        $this->assertValidAddresses(array_keys($recipients));

        $message = (new \Swift_Message($subject))
            ->setFrom($sender)
            ->setTo($recipients);

        foreach ($contents as $contentType => $data) {
            $message->addPart($data, $contentType);
        }

        return $message;
    }

    /**
     * @throws MailAddressValidationException
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
            throw new MailAddressValidationException($violations->__toString());
        }
    }
}
