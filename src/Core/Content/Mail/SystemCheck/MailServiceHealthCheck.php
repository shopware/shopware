<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\SystemCheck;

use Shopware\Core\Content\Mail\Service\MailSender;
use Shopware\Core\Content\Mail\Transport\MailerTransportLoader;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

/**
 * @internal
 */
#[Package('after-sales')]
class MailServiceHealthCheck extends BaseCheck
{
    public const NAME = 'MailServiceHealthCheck';

    public const MESSAGE_SUCCESS = 'Mail service is up and running.';

    public const MESSAGE_FAILURE = 'Mail service is not reachable.';

    public const MESSAGE_SKIPPED = 'The configured mail transport does not support a health check.';

    public function __construct(
        private readonly SystemConfigService $configService,
        private readonly MailerTransportLoader $mailerTransportLoader,
    ) {
    }

    public function category(): Category
    {
        return Category::EXTERNAL;
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(): Result
    {
        if ($this->configService->get(MailSender::DISABLE_MAIL_DELIVERY)) {
            return $this->buildResult(Status::SKIPPED);
        }

        try {
            $status = $this->testConnection();

            return $this->buildResult($status);
        } catch (\Throwable) {
            return $this->buildResult(Status::ERROR);
        }
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::longRunning();
    }

    private function testConnection(): Status
    {
        $dsn = (string) EnvironmentHelper::getVariable('MAILER_DSN', '');

        if (str_starts_with($dsn, 'null://')) {
            return Status::OK;
        }

        $transportConfig = trim($this->configService->getString('core.mailerSettings.emailAgent'));

        if (empty($transportConfig)) {
            return $this->testEnvTransport($dsn);
        }

        $reflection = new \ReflectionClass($this->mailerTransportLoader);
        $transport = $reflection->getMethod('create')
            ->invoke($this->mailerTransportLoader);

        if ($transport instanceof SmtpTransport) {
            return $this->testSmtpTransport($transport);
        }

        if ($transport instanceof SendmailTransport) {
            return $this->testLocalTransport();
        }

        return Status::SKIPPED;
    }

    private function testSmtpTransport(SmtpTransport $transport): Status
    {
        try {
            $transport->start();
            $transport->stop();
        } catch (\Exception) {
            return Status::ERROR;
        }

        return Status::OK;
    }

    private function testEnvTransport(string $dsn): Status
    {
        if (empty($dsn)) {
            return Status::SKIPPED;
        }

        if (str_starts_with($dsn, 'smtp://') || str_starts_with($dsn, 'smtps://')) {
            $host = (string) parse_url($dsn, \PHP_URL_HOST);
            $port = (int) parse_url($dsn, \PHP_URL_PORT);

            return $this->testSmtpTransportPrimitive($host, $port);
        }

        if (str_starts_with($dsn, 'sendmail://')) {
            return $this->testLocalTransport($dsn);
        }

        return Status::SKIPPED;
    }

    private function testLocalTransport(?string $dsn = null): Status
    {
        if ($dsn) {
            $command = urldecode(substr($dsn, 11));

            if ($command === 'default') {
                $command = '/usr/sbin/sendmail -t';
            }
        } else {
            $sendMailOptions = trim($this->configService->getString('core.mailerSettings.sendMailOptions'));

            if ($sendMailOptions === '') {
                $sendMailOptions = '-t';
            }

            $command = '/usr/sbin/sendmail ' . $sendMailOptions;
        }

        $executable = explode(' ', $command)[0];

        if (is_executable($executable)) {
            return Status::OK;
        }

        return Status::ERROR;
    }

    private function testSmtpTransportPrimitive(string $host, int $port): Status
    {
        $encryption = match ($this->configService->getString('core.mailerSettings.encryption')) {
            'ssl' => 'ssl://',
            'tls' => 'tls://',
            default => null,
        };

        if ($encryption !== null) {
            $host = $encryption . $host;
        }

        $stream = @stream_socket_client(
            $host . ':' . $port,
            $errno,
            $errstr,
            2
        );

        if ($stream === false) {
            return Status::ERROR;
        }

        $response = fgets($stream);
        fclose($stream);

        if ($response === false || !str_starts_with($response, '220')) {
            return Status::ERROR;
        }

        return Status::OK;
    }

    private function buildResult(Status $status): Result
    {
        $message = match ($status) {
            Status::OK => self::MESSAGE_SUCCESS,
            Status::ERROR => self::MESSAGE_FAILURE,
            default => self::MESSAGE_SKIPPED,
        };

        return new Result(
            $this->name(),
            $status,
            $message,
            $status === Status::OK,
        );
    }
}
