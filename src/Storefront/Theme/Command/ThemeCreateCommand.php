<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class ThemeCreateCommand extends Command
{
    protected static $defaultName = 'theme:create';

    private string $projectDir;

    public function __construct(string $projectDir)
    {
        parent::__construct();

        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('theme-name', InputArgument::OPTIONAL, 'Theme name')
            ->setDescription('Creates a theme skeleton');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $themeName = $input->getArgument('theme-name');

        if (!$themeName) {
            $question = new Question('Please enter a theme name: ');
            $themeName = $this->getHelper('question')->ask($input, $output, $question);
        }

        if (preg_match('/^[A-Za-z]\w{3,}$/', $themeName) !== 1) {
            $io->error('Theme name is too short (min 4 characters), contains invalid characters');

            return self::FAILURE;
        }

        $snakeCaseName = (new CamelCaseToSnakeCaseNameConverter())->normalize($themeName);
        $snakeCaseName = str_replace('_', '-', $snakeCaseName);

        $pluginName = ucfirst($themeName);

        $directory = $this->projectDir . '/custom/plugins/' . $pluginName;

        if (file_exists($directory)) {
            $io->error(sprintf('Plugin directory %s already exists', $directory));

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
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $composerFile = $directory . '/composer.json';
        $bootstrapFile = $directory . '/src/' . $pluginName . '.php';
        $themeConfigFile = $directory . '/src/Resources/theme.json';
        $variableOverridesFile = $directory . '/src/Resources/app/storefront/src/scss/overrides.scss';

        $composer = str_replace(
            ['#namespace#', '#class#'],
            [$pluginName, $pluginName],
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

        file_put_contents($composerFile, $composer);
        file_put_contents($bootstrapFile, $bootstrap);
        file_put_contents($themeConfigFile, $themeConfig);
        file_put_contents($variableOverridesFile, $this->getVariableOverridesTemplate());

        touch($directory . '/src/Resources/app/storefront/src/assets/.gitkeep');
        touch($directory . '/src/Resources/app/storefront/src/scss/base.scss');
        touch($directory . '/src/Resources/app/storefront/src/main.js');
        touch($directory . '/src/Resources/app/storefront/dist/storefront/js/' . $snakeCaseName . '.js');

        return self::SUCCESS;
    }

    /**
     * @throws \RuntimeException
     */
    private function createDirectory(string $pathName): void
    {
        if (!mkdir($pathName, 0755, true) && !is_dir($pathName)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s". Please check permissions', $pathName));
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
  "name": "swag/theme-skeleton",
  "description": "Theme skeleton plugin",
  "type": "shopware-platform-plugin",
  "license": "MIT",
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
    "app/storefront/dist/storefront/js/#snake-case#.js"
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
https://getbootstrap.com/docs/4.0/getting-started/theming/#variable-defaults
*/
EOL;
    }
}
