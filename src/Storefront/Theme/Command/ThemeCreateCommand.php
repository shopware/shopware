<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

#[Package('discovery')]
#[AsCommand(
    name: 'theme:create',
    description: 'Create a new theme',
)]
class ThemeCreateCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('theme-name', InputArgument::OPTIONAL, 'Theme name')
            ->addOption('static', null, null, 'Theme will be created in the static-plugins folder')
            ->addOption('full', null, null, 'Also scaffold a theme config, snippet files, and an SCSS folder structure (shorthand for --with-config --with-snippets --with-scss)')
            ->addOption('with-config', null, null, 'Also scaffold a theme config.xml')
            ->addOption('with-snippets', null, null, 'Also scaffold storefront snippet files')
            ->addOption('with-scss', null, null, 'Also scaffold an SCSS 7-1 folder structure');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $themeName = $input->getArgument('theme-name');
        $staticPrefix = $input->getOption('static') ? 'static-' : '';
        $full = (bool) $input->getOption('full');
        $withConfig = $full || $input->getOption('with-config');
        $withSnippets = $full || $input->getOption('with-snippets');
        $withScss = $full || $input->getOption('with-scss');

        if (!$themeName) {
            $question = new Question('Please enter a theme name: ');
            $questionHelper = $this->getHelper('question');
            \assert($questionHelper instanceof QuestionHelper);
            $themeName = $questionHelper->ask($input, $output, $question);
        }

        if (!ctype_upper((string) $themeName[0])) {
            $io->error('The name must start with an uppercase character');

            return self::FAILURE;
        }

        if (preg_match('/^[A-Za-z]\w{3,}$/', (string) $themeName) !== 1) {
            $io->error('Theme name is too short (min 4 characters), contains invalid characters');

            return self::FAILURE;
        }

        $snakeCaseName = (new CamelCaseToSnakeCaseNameConverter())->normalize($themeName);
        $snakeCaseName = str_replace('_', '-', $snakeCaseName);

        $pluginName = ucfirst((string) $themeName);

        $directory = \sprintf('%s/custom/%splugins/%s', $this->projectDir, $staticPrefix, $pluginName);

        if (\is_dir($directory)) {
            $io->error(\sprintf('Plugin directory %s already exists', $directory));

            return self::FAILURE;
        }

        $io->writeln('Creating theme structure under ' . $directory);

        try {
            $this->createDirectory($directory . '/src/Resources/app/');
            $this->createDirectory($directory . '/src/Resources/app/storefront/');
            $this->createDirectory($directory . '/src/Resources/app/storefront/src/');
            $this->createDirectory($directory . '/src/Resources/app/storefront/src/scss');
            $this->createDirectory($directory . '/src/Resources/app/storefront/src/assets');
            $this->createDirectory($directory . '/src/Resources/app/storefront/dist');
            $this->createDirectory($directory . '/src/Resources/app/storefront/dist/storefront');
            $this->createDirectory($directory . '/src/Resources/app/storefront/dist/storefront/js');
            $this->createDirectory($directory . '/src/Resources/app/storefront/dist/storefront/js/' . $snakeCaseName);

            if ($withScss) {
                $this->createDirectory($directory . '/src/Resources/app/storefront/src/scss/abstracts');
                $this->createDirectory($directory . '/src/Resources/app/storefront/src/scss/base');
                $this->createDirectory($directory . '/src/Resources/app/storefront/src/scss/components');
                $this->createDirectory($directory . '/src/Resources/app/storefront/src/scss/layout');
                $this->createDirectory($directory . '/src/Resources/app/storefront/src/scss/pages');
            }

            if ($withConfig) {
                $this->createDirectory($directory . '/src/Resources/config');
            }

            if ($withSnippets) {
                $this->createDirectory($directory . '/src/Resources/snippet');
            }
        } catch (ThemeException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $composerFile = $directory . '/composer.json';
        $bootstrapFile = $directory . '/src/' . $pluginName . '.php';
        $themeConfigFile = $directory . '/src/Resources/theme.json';
        $variableOverridesFile = $directory . '/src/Resources/app/storefront/src/scss/overrides.scss';

        $composer = str_replace(
            ['#namespace#', '#class#', '#composer-name#'],
            [$pluginName, $pluginName, 'custom/' . $snakeCaseName],
            $this->getComposerTemplate()
        );

        $bootstrap = str_replace(
            ['#namespace#', '#class#'],
            [$pluginName, $pluginName],
            $this->getBootstrapTemplate()
        );

        $themeConfig = str_replace(
            ['#name#', '#snake-case#'],
            [$themeName, $snakeCaseName],
            $this->getThemeConfigTemplate()
        );

        $this->filesystem->dumpFile($composerFile, $composer);
        $this->filesystem->dumpFile($bootstrapFile, $bootstrap);
        $this->filesystem->dumpFile($themeConfigFile, $themeConfig);
        $this->filesystem->dumpFile($variableOverridesFile, $this->getVariableOverridesTemplate());

        $this->filesystem->touch($directory . '/src/Resources/app/storefront/src/assets/.gitkeep');
        $this->filesystem->touch($directory . '/src/Resources/app/storefront/src/main.js');
        $this->filesystem->touch($directory . '/src/Resources/app/storefront/dist/storefront/js/' . $snakeCaseName . '/' . $snakeCaseName . '.js');

        if ($withConfig) {
            $this->filesystem->dumpFile($directory . '/src/Resources/config/config.xml', $this->getConfigTemplate());
        }

        if ($withSnippets) {
            $this->filesystem->dumpFile($directory . '/src/Resources/snippet/storefront.de-DE.json', $this->getSnippetTemplate());
            $this->filesystem->dumpFile($directory . '/src/Resources/snippet/storefront.en-GB.json', $this->getSnippetTemplate());
        }

        if ($withScss) {
            $this->filesystem->dumpFile($directory . '/src/Resources/app/storefront/src/scss/base.scss', $this->getBaseScssTemplate());
            $this->filesystem->dumpFile($directory . '/src/Resources/app/storefront/src/scss/abstracts/_variables.scss', "// Theme-specific SCSS variables.\n");
            $this->filesystem->dumpFile($directory . '/src/Resources/app/storefront/src/scss/abstracts/_mixins.scss', "// Theme-specific SCSS mixins.\n");
            $this->filesystem->dumpFile($directory . '/src/Resources/app/storefront/src/scss/base/_reset.scss', "// Base element resets.\n");
            $this->filesystem->dumpFile($directory . '/src/Resources/app/storefront/src/scss/base/_typography.scss', "// Base typography styles.\n");
            $this->filesystem->dumpFile($directory . '/src/Resources/app/storefront/src/scss/components/_buttons.scss', "// Button component styles.\n");
            $this->filesystem->dumpFile($directory . '/src/Resources/app/storefront/src/scss/layout/_header.scss', "// Header layout styles.\n");
            $this->filesystem->dumpFile($directory . '/src/Resources/app/storefront/src/scss/layout/_footer.scss', "// Footer layout styles.\n");
            $this->filesystem->dumpFile($directory . '/src/Resources/app/storefront/src/scss/layout/_navigation.scss', "// Navigation layout styles.\n");
            $this->filesystem->dumpFile($directory . '/src/Resources/app/storefront/src/scss/pages/_home.scss', "// Homepage-specific styles.\n");
        } else {
            $this->filesystem->touch($directory . '/src/Resources/app/storefront/src/scss/base.scss');
        }

        return self::SUCCESS;
    }

    private function createDirectory(string $pathName): void
    {
        try {
            $this->filesystem->mkdir($pathName, 0755);
        } catch (IOException $e) {
            throw ThemeException::themeCreationFailure(\sprintf('Unable to create directory "%s". Please check permissions', $pathName));
        }
    }

    private function getBootstrapTemplate(): string
    {
        return <<<EOL
<?php declare(strict_types=1);

namespace #namespace#;

use Shopware\Core\Framework\Plugin;
use Shopware\Storefront\Framework\ThemeInterface;

class #class# extends Plugin implements ThemeInterface
{
}
EOL;
    }

    private function getComposerTemplate(): string
    {
        return <<<EOL
{
  "name": "#composer-name#",
  "description": "Theme skeleton plugin",
  "type": "shopware-platform-plugin",
  "license": "MIT",
  "require": {
    "shopware/core": "~6.7.0"
  },
  "autoload": {
    "psr-4": {
      "#namespace#\\\\": "src/"
    }
  },
  "extra": {
    "shopware-plugin-class": "#namespace#\\\\#class#",
    "label": {
      "de-DE": "Theme #namespace# plugin",
      "en-GB": "Theme #namespace# plugin"
    }
  }
}
EOL;
    }

    private function getConfigTemplate(): string
    {
        return <<<EOL
<?xml version="1.0" encoding="UTF-8"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/System/SystemConfig/Schema/config.xsd">

    <card>
        <title>Theme configuration</title>

        <input-field type="text">
            <name>exampleField</name>
            <label>Example field</label>
        </input-field>
    </card>

</config>
EOL;
    }

    private function getSnippetTemplate(): string
    {
        return <<<EOL
{
}
EOL;
    }

    private function getBaseScssTemplate(): string
    {
        return <<<EOL
// Abstracts
@import "abstracts/variables";
@import "abstracts/mixins";

// Base
@import "base/reset";
@import "base/typography";

// Components
@import "components/buttons";

// Layout
@import "layout/header";
@import "layout/footer";
@import "layout/navigation";

// Pages
@import "pages/home";
EOL;
    }

    private function getThemeConfigTemplate(): string
    {
        return <<<EOL
{
  "name": "#name#",
  "author": "Shopware AG",
  "views": [
     "@Storefront",
     "@Plugins",
     "@#name#"
  ],
  "style": [
    "app/storefront/src/scss/overrides.scss",
    "@Storefront",
    "app/storefront/src/scss/base.scss"
  ],
  "script": [
    "@Storefront",
    "app/storefront/dist/storefront/js/#snake-case#/#snake-case#.js"
  ],
  "asset": [
    "@Storefront",
    "app/storefront/src/assets"
  ]
}
EOL;
    }

    private function getVariableOverridesTemplate(): string
    {
        return <<<EOL
/*
Override variable defaults
==================================================
This file is used to override default SCSS variables from the Shopware Storefront or Bootstrap.

Because of the !default flags, theme variable overrides have to be declared beforehand.
https://getbootstrap.com/docs/5.3/customize/sass/#variable-defaults
*/
EOL;
    }
}
