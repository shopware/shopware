<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Symfony\XmlServiceMapFactory;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\ServiceDefinitionCollector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\ServiceDefinitionRule;

/**
 * @internal
 *
 * @extends RuleTestCase<ServiceDefinitionRule>
 */
class ServiceDefinitionRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $fixtureDir = __DIR__ . '/data/ServiceDefinitionRule';

        $this->analyse([
            $fixtureDir . '/src/Core/Framework/Example/CoreContract.php',
            $fixtureDir . '/src/Core/Framework/Example/CoreServiceInCore.php',
            $fixtureDir . '/src/Core/Framework/Example/PhpCoreService.php',
            $fixtureDir . '/src/Core/Framework/Example/XmlCoreService.php',
        ], [
            [
                'src/Storefront/DependencyInjection/services.php - service "Shopware\Core\Framework\Example\PhpCoreService" is registered in Storefront but its effective class "Shopware\Core\Framework\Example\PhpCoreService" belongs to Core. Register it in a Core DependencyInjection file instead.',
                1,
            ],
            [
                'src/Storefront/DependencyInjection/services.xml - service "Shopware\Core\Framework\Example\XmlCoreService" is registered in Storefront but its effective class "Shopware\Core\Framework\Example\XmlCoreService" belongs to Core. Register it in a Core DependencyInjection file instead.',
                1,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        $fixtureDir = __DIR__ . '/data/ServiceDefinitionRule';

        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory($fixtureDir . '/container.xml');

        /** @phpstan-ignore phpstanApi.method */
        return new ServiceDefinitionRule($factory->create(), $fixtureDir);
    }

    /**
     * @return list<ServiceDefinitionCollector>
     */
    protected function getCollectors(): array
    {
        $fixtureDir = __DIR__ . '/data/ServiceDefinitionRule';

        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory($fixtureDir . '/container.xml');

        /** @phpstan-ignore phpstanApi.method */
        $serviceMap = $factory->create();

        return [
            new ServiceDefinitionCollector($serviceMap),
        ];
    }
}
