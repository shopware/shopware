<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Ucp\Jwt\UcpSigningKeyProvider;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @internal
 */
#[AsMessageHandler(handles: UcpKeyRetirementTask::class)]
#[Package('framework')]
final class UcpKeyRetirementTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly UcpSigningKeyProvider $signingKeyProvider,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        // Honour UCP signatures.md RECOMMENDED 7-day grace before a retiring
        // key is fully retired. Keys can still verify (but not sign) during
        // this window.
        $threshold = (new \DateTimeImmutable())->modify(
            '-' . UcpSigningKeyProvider::RETIREMENT_GRACE_PERIOD_SECONDS . ' seconds'
        );
        $context = Context::createCLIContext();
        $keys = $this->signingKeyProvider->findKeysReadyForRetirement($threshold, $context);

        foreach ($keys as $key) {
            $this->signingKeyProvider->transitionRetiringToRetired($key->getKid(), $context);
        }
    }
}
