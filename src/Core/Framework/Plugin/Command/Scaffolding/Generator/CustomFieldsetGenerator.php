<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[Package('core')]
class CustomFieldsetGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-custom-fieldset';
    private const OPTION_DESCRIPTION = 'Create an example custom fieldset';
    private const CLI_QUESTION = 'Do you want to create an example custom fieldset?';

    private string $servicesXmlEntry = <<<'EOL'

            <service id="{{ namespace }}\Service\CustomFieldsInstaller">
                <argument type="service" id="custom_field_set.repository"/>
                <argument type="service" id="custom_field_set_relation.repository"/>
            </service>

    EOL;

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add($this->createInstaller($configuration));
        $stubCollection->add($this->createPluginClassWithCustomFields($configuration));

        $stubCollection->append(
            'src/Resources/config/services.xml',
            str_replace(
                '{{ namespace }}',
                $configuration->namespace,
                $this->servicesXmlEntry
            )
        );
    }

    public function createPluginClassWithCustomFields(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/' . $configuration->name . '.php',
            self::STUB_DIRECTORY . '/plugin-class-with-custom-fields.stub',
            [
                'namespace' => $configuration->namespace,
                'className' => $configuration->name,
            ]
        );
    }

    private function createInstaller(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Service/CustomFieldsInstaller.php',
            self::STUB_DIRECTORY . '/custom-fieldset-installer.stub',
            [
                'namespace' => $configuration->namespace,
            ]
        );
    }
}
