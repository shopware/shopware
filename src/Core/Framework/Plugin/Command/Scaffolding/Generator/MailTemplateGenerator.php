<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[Package('after-sales')]
class MailTemplateGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-mail-template';
    private const OPTION_DESCRIPTION = 'Create an example mail template';
    private const CLI_QUESTION = 'Do you want to create an example mail template?';

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add(Stub::template(
            'src/Resources/mail-templates/mail-templates.xml',
            self::STUB_DIRECTORY . '/mail-templates-xml.stub'
        ));

        $stubCollection->add(Stub::template(
            'src/Resources/mail-templates/example_notification/en-GB/html.twig',
            self::STUB_DIRECTORY . '/mail-template-html.stub'
        ));

        $stubCollection->add(Stub::template(
            'src/Resources/mail-templates/example_notification/en-GB/plain.twig',
            self::STUB_DIRECTORY . '/mail-template-plain.stub'
        ));
    }
}
