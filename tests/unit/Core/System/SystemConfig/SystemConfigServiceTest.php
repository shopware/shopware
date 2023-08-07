<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\AbstractSystemConfigLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\SystemConfig\SystemConfigService
 */
class SystemConfigServiceTest extends TestCase
{
    /**
     * @param array<string> $tags
     *
     * @dataProvider provideTracingExamples
     */
    public function testTracing(bool $enabled, array $tags): void
    {
        $config = new SystemConfigService(
            $this->createMock(Connection::class),
            $this->createMock(ConfigReader::class),
            $this->createMock(AbstractSystemConfigLoader::class),
            new EventDispatcher(),
            $enabled
        );

        $config->trace('test', function () use ($config): void {
            $config->get('test');
        });

        static::assertSame($tags, $config->getTrace('test'));
    }

    public static function provideTracingExamples(): \Generator
    {
        yield 'disabled' => [
            false,
            [
                'global.system.config',
            ],
        ];

        yield 'enabled' => [
            true,
            [
                'config.test',
            ],
        ];
    }
}
