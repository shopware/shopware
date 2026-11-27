<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Export;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Export\Summarizer\PanelSummarizer;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * @internal
 *
 * @phpstan-type ProfileListEntry array{token: string, type: string, method: string, url: string, status: int|null, time: string, ip: string, parent: string|null}
 */
#[Package('framework')]
class ProfileExporter
{
    public const LATEST = 'latest';

    private const CURATED_MIN_STRING = 500;

    private const PANEL_ALIASES = [
        'app.connection_collector' => 'db',
    ];

    /**
     * @param iterable<PanelSummarizer> $summarizers
     */
    public function __construct(
        private readonly Profiler $profiler,
        private readonly CollectorDataConverter $converter,
        private readonly SecretRedactor $redactor,
        private readonly iterable $summarizers,
    ) {
    }

    /**
     * @return list<ProfileListEntry>
     */
    public function list(?string $url = null, ?string $method = null, ?string $status = null, string $type = 'request', int $limit = 20, bool $redact = true): array
    {
        $profiles = $this->profiler->find(null, $url, $limit, $method, null, null, $status, static fn (array $profile): bool => $profile['virtual_type'] === $type);

        return array_values(array_map(fn (array $profile): array => [
            'token' => (string) $profile['token'],
            'type' => (string) $profile['virtual_type'],
            'method' => (string) $profile['method'],
            'url' => $redact ? $this->redactor->redactString((string) $profile['url']) : (string) $profile['url'],
            'status' => is_numeric($profile['status_code']) ? (int) $profile['status_code'] : null,
            'time' => self::formatTime((int) $profile['time']),
            'ip' => (string) $profile['ip'],
            'parent' => $profile['parent'] === '' || $profile['parent'] === null ? null : (string) $profile['parent'],
        ], $profiles));
    }

    public function load(string $token, ?string $url = null, ?string $method = null, ?string $status = null, string $type = 'request'): ?Profile
    {
        if ($token === self::LATEST) {
            $token = $this->list($url, $method, $status, $type, 1)[0]['token'] ?? null;
        }

        return $token === null ? null : $this->profiler->loadProfile($token);
    }

    /**
     * @return array<string, string> collector name => panel name
     */
    public function panels(Profile $profile): array
    {
        $names = array_keys($profile->getCollectors());
        $taken = array_fill_keys($names, true);

        $panels = [];
        foreach ($names as $name) {
            $alias = self::PANEL_ALIASES[$name] ?? self::shortName($name);
            $panels[$name] = $alias === $name || isset($taken[$alias]) ? $name : $alias;
            $taken[$panels[$name]] = true;
        }

        return $panels;
    }

    /**
     * @param list<string> $panels collector or panel names, empty for all
     *
     * @return array<string, mixed>
     */
    public function export(Profile $profile, array $panels = [], bool $full = false, ?ExportLimits $limits = null, bool $redact = true): array
    {
        $limits ??= $full ? ExportLimits::full() : ExportLimits::summary();
        $curatedLimits = new ExportLimits(depth: 0, items: 0, string: $limits->string <= 0 ? 0 : max($limits->string, self::CURATED_MIN_STRING));
        $redactor = $redact ? $this->redactor->withKnownSecrets($this->learnSecrets($profile)) : null;

        $exported = [];
        foreach ($this->panels($profile) as $name => $panel) {
            if ($panels !== [] && !\in_array($panel, $panels, true) && !\in_array($name, $panels, true)) {
                continue;
            }

            $collector = $profile->getCollector($name);
            $data = self::data($collector);
            $summarizer = $full ? null : $this->summarizer($collector);

            $exported[$panel] = $summarizer === null
                ? $this->converter->convert($data, $limits, $redactor)
                : $this->converter->convert($summarizer->summarize($collector, $data), $curatedLimits, $redactor);
        }

        return [
            'token' => $profile->getToken(),
            'type' => $profile->getVirtualType() ?? 'request',
            'method' => $profile->getMethod(),
            'url' => $this->converter->convert($profile->getUrl(), $curatedLimits, $redactor),
            'status' => $profile->getStatusCode(),
            'time' => self::formatTime($profile->getTime()),
            'ip' => $profile->getIp(),
            'parent' => $profile->getParentToken(),
            'children' => array_map(fn (Profile $child): array => [
                'token' => $child->getToken(),
                'method' => $child->getMethod(),
                'url' => $this->converter->convert($child->getUrl(), $curatedLimits, $redactor),
                'status' => $child->getStatusCode(),
            ], $profile->getChildren()),
            'redacted' => $redact,
            'mode' => $full ? 'full' : 'summary',
            'panels' => $exported,
        ];
    }

    /**
     * @return list<string>
     */
    private function learnSecrets(Profile $profile): array
    {
        $secrets = [];
        foreach ($profile->getCollectors() as $collector) {
            if ($collector instanceof RequestDataCollector) {
                $secrets = [...$secrets, ...$this->redactor->learnSecrets(self::data($collector))];
            }
        }

        return $secrets;
    }

    private static function data(DataCollectorInterface $collector): mixed
    {
        return $collector instanceof DataCollector ? (new \ReflectionProperty(DataCollector::class, 'data'))->getValue($collector) : $collector;
    }

    private function summarizer(DataCollectorInterface $collector): ?PanelSummarizer
    {
        foreach ($this->summarizers as $summarizer) {
            if ($summarizer->supports($collector)) {
                return $summarizer;
            }
        }

        return null;
    }

    private static function shortName(string $name): string
    {
        if (!str_contains($name, '\\')) {
            return $name;
        }

        $short = substr($name, (int) strrpos($name, '\\') + 1);
        $short = (string) preg_replace('/(DataCollectorSubscriber|CollectorSubscriber|DataCollector|Collector|Profiler)$/D', '', $short) ?: $short;

        return mb_strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
    }

    private static function formatTime(int $timestamp): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))->format(\DATE_ATOM);
    }
}
