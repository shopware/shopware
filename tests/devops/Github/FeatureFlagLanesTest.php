<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Github;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

require_once __DIR__ . '/../../../.github/bin/lib/feature-flags.php';

/**
 * The matrix generators derive one test lane per in-flight major from the feature flag registry.
 * They run in a bare checkout, so they read `feature.yaml` with their own reader instead of the
 * Symfony YAML component — these tests cover that reader and the lane derivation on top of it.
 *
 * @internal
 */
#[Package('framework')]
class FeatureFlagLanesTest extends TestCase
{
    private const REGISTRY = <<<'YAML'
        shopware:
          feature:
            flags:
              - name: v6.7.0.0
                default: true
                major: true
                toggleable: false
              - name: v6.8.0.0
                default: false
                major: true
                toggleable: false
              - name: v6.9.0.0
                default: false
                major: true
                toggleable: false
              - name: JSON_LD_DATA
                default: false
                major: true
                toggleable: true
                description: "Replace inline microdata with JSON-LD: not named after a major."
              - name: TELEMETRY_METRICS
                default: false
                major: false
                toggleable: true
        YAML;

    /**
     * @var list<string>
     */
    private array $registries = [];

    protected function tearDown(): void
    {
        foreach ($this->registries as $registry) {
            \unlink($registry);
        }

        $this->registries = [];
    }

    public function testReadsEveryFlagWithItsScalarOptions(): void
    {
        $flags = shopware_read_feature_flags($this->registry(self::REGISTRY));

        static::assertSame(['v6.7.0.0', 'v6.8.0.0', 'v6.9.0.0', 'JSON_LD_DATA', 'TELEMETRY_METRICS'], array_keys($flags));
        static::assertSame(['default' => 'false', 'major' => 'true', 'toggleable' => 'true'], $flags['JSON_LD_DATA']);
    }

    public function testInFlightMajorsAreTheUnreleasedVersionedMajors(): void
    {
        static::assertSame(['v6.8.0.0', 'v6.9.0.0'], shopware_in_flight_majors($this->registry(self::REGISTRY)));
    }

    public function testInFlightMajorsAreSortedByVersion(): void
    {
        $registry = str_replace(
            ['- name: v6.8.0.0', '- name: v6.9.0.0'],
            ['- name: v6.10.0.0', '- name: v6.9.0.0'],
            self::REGISTRY
        );

        static::assertSame(['v6.9.0.0', 'v6.10.0.0'], shopware_in_flight_majors($this->registry($registry)));
    }

    public function testLanesFallBackToAllMajorsWhenEveryMajorHasShipped(): void
    {
        $registry = str_replace('default: false', 'default: true', self::REGISTRY);

        static::assertSame([], shopware_in_flight_majors($this->registry($registry)));
        static::assertSame(['major'], shopware_major_lanes($this->registry($registry)));
    }

    public function testRejectsARegistryWithoutAnyFlag(): void
    {
        $this->expectException(\RuntimeException::class);

        shopware_read_feature_flags($this->registry('shopware:'));
    }

    public function testRejectsAMissingRegistry(): void
    {
        $this->expectException(\RuntimeException::class);

        shopware_read_feature_flags(\sys_get_temp_dir() . '/feature-' . \uniqid() . '.yaml');
    }

    public function testDerivesLanesFromTheRegistryOfThisBranch(): void
    {
        $lanes = shopware_major_lanes();

        static::assertNotEmpty($lanes, 'Without a lane the major matrix would run no job at all.');

        $flags = shopware_read_feature_flags();
        foreach ($lanes as $lane) {
            if ($lane === 'major') {
                static::assertCount(1, $lanes, 'The all-majors fallback is only valid as the single lane.');

                continue;
            }

            static::assertArrayHasKey($lane, $flags, \sprintf('Lane "%s" is not a registered feature flag.', $lane));
            static::assertSame('true', $flags[$lane]['major'] ?? null);
            static::assertSame('false', $flags[$lane]['default'] ?? null);
        }
    }

    private function registry(string $contents): string
    {
        $path = \sys_get_temp_dir() . '/feature-' . \uniqid() . '.yaml';
        \file_put_contents($path, $contents);
        $this->registries[] = $path;

        return $path;
    }
}
