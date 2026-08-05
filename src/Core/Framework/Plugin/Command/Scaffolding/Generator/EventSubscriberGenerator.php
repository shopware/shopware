<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[Package('framework')]
class EventSubscriberGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-event-subscriber';
    private const OPTION_DESCRIPTION = 'Create an example event subscriber';
    private const CLI_QUESTION = 'Do you want to create an example event subscriber?';

    private string $servicesPhpEntry = <<<'EOL'

    $services->set(\{{ namespace }}\Subscriber\MySubscriber::class)
        ->tag('kernel.event_subscriber');

EOL;

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add($this->createSubscriber($configuration));

        $stubCollection->append(
            'src/Resources/config/services.php',
            str_replace(
                '{{ namespace }}',
                $configuration->namespace,
                $this->servicesPhpEntry
            )
        );
    }

    private function createSubscriber(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Subscriber/MySubscriber.php',
            self::STUB_DIRECTORY . '/event-subscriber.stub',
            [
                'namespace' => $configuration->namespace,
            ]
        );
    }
}
