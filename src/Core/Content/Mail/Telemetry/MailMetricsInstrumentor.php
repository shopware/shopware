<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Instrumentation\ElapsedTimer;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * Telemetry collaborator for {@see \Shopware\Core\Content\Mail\Service\MailService}: wraps the send handoff
 * (`AbstractMailSender::send()`) and emits `mail.send.duration` and `mail.send.count` per attempt.
 *
 * On a default install mail delivery is async: `AbstractMailSender::send()` hands the mail off to the message
 * queue (Symfony's `SendEmailMessage`, or Shopware's `SendMailMessage` for oversized mails) and returns before
 * the transport runs. So `result=sent` means "handed off", not "delivered", and the timed duration is the handoff
 * time. The transport round-trip itself happens in a worker and is covered by `messenger.message.handling.duration`
 * (label `message_group=mail`). Only a synchronous mailer transport — uncommon in production — makes this the full
 * send time; that is acceptable here.
 *
 * Times manually on `Meter`: the `result` label is only known once the handoff returns or throws.
 *
 * Merely-hot path: relies on `Meter::emit`'s early-return when telemetry is disabled, no compiler-pass gating.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('after-sales')]
class MailMetricsInstrumentor
{
    private const RESULT_SENT = 'sent';
    private const RESULT_FAILED = 'failed';

    public function __construct(
        private readonly Meter $meter,
        private readonly MailGroupResolver $mailGroupResolver,
    ) {
    }

    /**
     * @param \Closure(): void $send
     */
    public function measureSend(?string $eventName, \Closure $send): void
    {
        $result = self::RESULT_SENT;
        $timer = ElapsedTimer::start();

        try {
            $send();
        } catch (\Throwable $e) {
            $result = self::RESULT_FAILED;

            throw $e;
        } finally {
            $this->meter->emit(new ConfiguredMetric(
                name: 'mail.send.duration',
                value: $timer->getElapsedMs(),
                labels: ['result' => $result],
            ));

            $this->meter->emit(new ConfiguredMetric(
                name: 'mail.send.count',
                value: 1,
                labels: [
                    'mail_group' => $this->mailGroupResolver->resolve($eventName),
                    'result' => $result,
                ],
            ));
        }
    }
}
