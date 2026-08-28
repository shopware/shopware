<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Tracking;

use Psr\Clock\ClockInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Anonymous usage tracking for CLI and other product surfaces.
 *
 * Honours `DO_NOT_TRACK`. Shares `core.telemetry.id` with the Deployment Helper
 * so the same installation can be correlated across tools.
 *
 * @internal
 *
 * @see https://developer.shopware.com/docs/resources/references/telemetry.html
 */
#[Package('framework')]
class TrackingService
{
    public const TELEMETRY_ID = 'core.telemetry.id';

    public const LEGACY_DEPLOYMENT_HELPER_ID = 'core.deployment_helper.id';

    public const TELEMETRY_DOCS_URL = 'https://developer.shopware.com/docs/resources/references/telemetry.html';

    private const EVENT_PREFIX = 'shopware.';

    /**
     * @var array<string, string>
     */
    private array $defaultTags;

    private string $id;

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly TrackingTransport $transport,
        private readonly ClockInterface $clock,
        private readonly string $shopwareVersion,
    ) {
    }

    /**
     * @param array<string, string|int|float> $tags
     */
    public function track(string $eventName, array $tags = []): void
    {
        if (EnvironmentHelper::hasVariable('DO_NOT_TRACK')) {
            return;
        }

        $payload = json_encode([
            'event' => self::EVENT_PREFIX . $eventName,
            'tags' => $tags + $this->getTags(),
            'user_id' => $this->getId(),
            'timestamp' => $this->clock->now()->format(\DateTimeInterface::ATOM),
        ], \JSON_THROW_ON_ERROR);

        $this->transport->send($payload);
    }

    public function persistId(): void
    {
        if (!isset($this->id)) {
            return;
        }

        try {
            $this->systemConfigService->set(self::TELEMETRY_ID, $this->id);
        } catch (\Throwable) {
        }
    }

    public function showHint(OutputInterface $output): void
    {
        if (EnvironmentHelper::hasVariable('DO_NOT_TRACK')) {
            return;
        }

        $output->writeln(\sprintf(
            '<comment>Shopware collects anonymous telemetry about your usage of scaffolding commands. Learn more at %s</comment>',
            self::TELEMETRY_DOCS_URL,
        ));
    }

    /**
     * @return array<string, string>
     */
    private function getTags(): array
    {
        if (isset($this->defaultTags)) {
            return $this->defaultTags;
        }

        $this->defaultTags = [
            'shopware_version' => $this->shopwareVersion,
            'php_version' => \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION,
        ];

        return $this->defaultTags;
    }

    private function getId(): string
    {
        if (isset($this->id)) {
            return $this->id;
        }

        try {
            $id = $this->systemConfigService->get(self::TELEMETRY_ID);
            if (\is_string($id) && $id !== '') {
                return $this->id = $id;
            }

            $legacyId = $this->systemConfigService->get(self::LEGACY_DEPLOYMENT_HELPER_ID);
            if (\is_string($legacyId) && $legacyId !== '') {
                $this->systemConfigService->set(self::TELEMETRY_ID, $legacyId);
                $this->systemConfigService->delete(self::LEGACY_DEPLOYMENT_HELPER_ID);

                return $this->id = $legacyId;
            }
        } catch (\Throwable) {
            return $this->id = bin2hex(random_bytes(16));
        }

        $this->id = bin2hex(random_bytes(16));

        try {
            $this->systemConfigService->set(self::TELEMETRY_ID, $this->id);
        } catch (\Throwable) {
        }

        return $this->id;
    }
}
