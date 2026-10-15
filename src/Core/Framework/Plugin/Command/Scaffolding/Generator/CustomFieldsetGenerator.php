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
class CustomFieldsetGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-custom-fieldset';
    private const OPTION_TITLE = 'Custom Fieldset';
    private const OPTION_DESCRIPTION = 'Create an example custom fieldset';
    private const OPTION_DESCRIPTION_LONG = 'Custom fields are additional data fields that extend existing Shopware entities without requiring a completely custom entity. Use them when you need to store simple, scalar values such as text, numbers, or selections; for entity relationships and complex / performance critical cases, use a custom entity instead, which gives you full control over the DB schema.';
    private const CLI_QUESTION = 'Do you want to create an example custom fieldset?';

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add(Stub::template(
            'src/Resources/config/custom-fields.xml',
            self::STUB_DIRECTORY . '/custom-fields-xml.stub'
        ));
    }
}
