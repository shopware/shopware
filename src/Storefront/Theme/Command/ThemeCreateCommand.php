<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class ThemeCreateCommand extends Command
{
    /**
     * @var string
     */
    private $projectDir;

    public function __construct(string $projectDir)
    {
        parent::__construct();
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this->setName('theme:create')
            ->addArgument('theme-name', InputArgument::OPTIONAL, 'Theme name')
            ->setDescription('Creates a plugin skeleton');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $name = $input->getArgument('theme-name');

        if (!$name) {
            $question = new Question('Please enter a theme name:');
            $name = $helper->ask($input, $output, $question);
        }

        if (preg_match('/^[A-Z]\w{3,}$/', $name) !== 1) {
            $io->error('Theme name is too short (min 4 characters), contains invalid characters or doesn\'t start with a uppercase character');

            return 1;
        }

        $directory = $this->projectDir . '/custom/plugins/' . $name;

        if (file_exists($directory)) {
            $io->error(sprintf('Plugin directory %s already exists', $directory));

            return 1;
        }

        $io->writeln('Creating theme structure under ' . $directory);

        try {
            $this->createDirectory($directory . '/src/Resources/app/');
            $this->createDirectory($directory . '/src/Resources/app/storefront/');
            $this->createDirectory($directory . '/src/Resources/app/storefront/src/');
            $this->createDirectory($directory . '/src/Resources/app/storefront/src/scss');
            $this->createDirectory($directory . '/src/Resources/app/storefront/src/asset');
            $this->createDirectory($directory . '/src/Resources/app/storefront/dist/script');
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return 1;
        }

        $composerFile = $directory . '/composer.json';
        $bootstrapFile = $directory . '/src/' . $name . '.php';
        $themeConfigFile = $directory . '/src/theme.json';

        $composer = str_replace(
            ['#namespace#', '#class#'],
            [$name, $name],
            $this->getComposerTemplate()
        );

        $bootstrap = str_replace(
            ['#namespace#', '#class#'],
            [$name, $name],
            $this->getBootstrapTemplate()
        );

        $themeConfig = str_replace(
            ['#name#'],
            [$name],
            $this->getThemeConfigTemplate()
        );

        file_put_contents($composerFile, $composer);
        file_put_contents($bootstrapFile, $bootstrap);
        file_put_contents($themeConfigFile, $themeConfig);

        touch($directory . '/src/Resources/app/storefront/dist/script/all.js');
        touch($directory . '/src/Resources/app/storefront/src/scss/base.scss');

        return null;
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
    public function getThemeConfigPath(): string
    {
        return 'theme.json';
    }
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
  "style": [
    "@Storefront",
    "Resources/app/storefront/scr/scss/base.scss"
  ],
  "script": [
    "@Storefront",
    "Resources/storefront/dist/script/all.js"
  ],
  "asset": [
    "Resources/app/storefront/src/asset"
  ]
}
EOL;
    }
}
