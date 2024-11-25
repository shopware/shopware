<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Message;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[AsMessageHandler(handles: SendMailMessage::class)]
#[Package('services-settings')]
final class SendMailHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly FilesystemOperator $filesystem,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableMessageHandlingException
     */
    public function __invoke(SendMailMessage $message): void
    {
        $mailDataPath = $message->getMailDataPath();

        try {
            $mailData = $this->filesystem->read($mailDataPath);
        } catch (\Throwable $e) {
            if (!$this->filesystem->fileExists($mailDataPath)) {
                throw new UnrecoverableMessageHandlingException(\sprintf('The mail data file "%s" does not exist. Mail could not be sent.', $mailDataPath), 0, $e);
            }

            throw $e;
        }

        $mail = unserialize($mailData);

        if (!($mail instanceof Email)) {
            $this->cleanup($message);

            return;
        }

        $this->transport->send($mail);

        $this->cleanup($message);
    }

    /**
     * @throws UnrecoverableMessageHandlingException
     */
    private function cleanup(SendMailMessage $message): void
    {
        $mailDataPath = $message->getMailDataPath();

        try {
            $this->filesystem->delete($mailDataPath);
        } catch (\Throwable $e) {
            throw new UnrecoverableMessageHandlingException(\sprintf('An error occurred while deleting the mail data file "%s".', $mailDataPath), 0, $e);
        }
    }
}
