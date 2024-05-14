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
class AdminModuleGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-admin-module';
    private const OPTION_DESCRIPTION = 'Create an example admin module';
    private const CLI_QUESTION = 'Do you want to create an example admin module?';

    private string $mainJsEntry = <<<'EOL'
    // Import admin module
    import './module/swag-example';

    EOL;

    private string $snippet = <<<'EOL'
    {
        "swag-example": {
            "general": {
                "mainMenuItemGeneral": "My custom module",
                "descriptionTextModule": "Manage this custom module here"
            }
        }
    }

    EOL;

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add($this->createModule());
        $stubCollection->add($this->createMainJsEntry());

        foreach ($this->createSnippets() as $snippet) {
            $stubCollection->add($snippet);
        }
    }

    private function createModule(): Stub
    {
        return Stub::template(
            'src/Resources/app/administration/src/module/swag-example/index.js',
            self::STUB_DIRECTORY . '/js-module.stub'
        );
    }

    private function createMainJsEntry(): Stub
    {
        return Stub::raw(
            'src/Resources/app/administration/src/main.js',
            $this->mainJsEntry,
        );
    }

    /**
     * @return Stub[]
     */
    private function createSnippets(): array
    {
        return [
            Stub::raw(
                'src/Resources/app/administration/src/snippet/en-GB.json',
                $this->snippet
            ),
            Stub::raw(
                'src/Resources/app/administration/src/snippet/de-DE.json',
                $this->snippet
            ),
        ];
    }
}
