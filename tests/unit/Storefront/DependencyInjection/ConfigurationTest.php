<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Configuration::class)]
class ConfigurationTest extends TestCase
{
    public function testClearSiteDataIsEmptyByDefault(): void
    {
        $config = $this->process([]);

        static::assertSame([], $config['security']['clear_site_data_on_logout']);
    }

    public function testSupportedDirectivesAreAccepted(): void
    {
        $config = $this->process([
            ['security' => ['clear_site_data_on_logout' => ['cache', 'cookies', 'storage']]],
        ]);

        static::assertSame(['cache', 'cookies', 'storage'], $config['security']['clear_site_data_on_logout']);
    }

    #[DataProvider('unsupportedDirectiveProvider')]
    public function testUnsupportedDirectivesAreRejected(string $directive): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            ['security' => ['clear_site_data_on_logout' => [$directive]]],
        ]);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsupportedDirectiveProvider(): iterable
    {
        yield 'executionContexts' => ['executionContexts'];
        yield 'clientHints' => ['clientHints'];
        yield 'wildcard' => ['*'];
        yield 'typo' => ['cookie'];
    }

    public function testLaterConfigsReplaceInsteadOfAppend(): void
    {
        $config = $this->process([
            ['security' => ['clear_site_data_on_logout' => ['cache', 'cookies']]],
            ['security' => ['clear_site_data_on_logout' => ['storage']]],
        ]);

        static::assertSame(['storage'], $config['security']['clear_site_data_on_logout']);
    }

    /**
     * @param list<array<string, mixed>> $configs
     *
     * @return array<string, mixed>
     */
    private function process(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }
}
