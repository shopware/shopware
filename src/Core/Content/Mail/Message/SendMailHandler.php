<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Message;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
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
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws FilesystemException
     */
    public function __invoke(SendMailMessage $message): void
    {
        $mailDataPath = $message->mailDataPath;
        try {
            $mailData = $this->filesystem->read($mailDataPath);
        } catch (FilesystemException $e) {
            if (!$this->filesystem->fileExists($mailDataPath)) {
                $this->logger->error('The mail data file does not exist. Mail could not be sent.', ['mailDataPath' => $mailDataPath, 'exception' => $e->getMessage()]);

                return;
            }

            throw $e;
        }

        $mail = unserialize($mailData);
        if (!is_a($mail, Email::class)) {
            $this->logger->error('The mail data file does not contain a valid email object. Mail could not be sent.', ['mailDataPath' => $mailDataPath]);

            return;
        }

        $this->transport->send($mail);
        $this->cleanup($message);
    }

    private function cleanup(SendMailMessage $message): void
    {
        $mailDataPath = $message->mailDataPath;

        try {
            $this->filesystem->delete($mailDataPath);
        } catch (FilesystemException $e) {
            $this->logger->error('Could not delete mail data file after sending mail.', ['mailDataPath' => $mailDataPath, 'exception' => $e->getMessage()]);
        }
    }
}
