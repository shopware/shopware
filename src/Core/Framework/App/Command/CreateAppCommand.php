<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use function Symfony\Component\String\u;

/**
 * @internal
 *
 * @phpstan-type PropertyDefinitions array<string, array{name: string, description: string, prompt: string, default: string, validator?: callable(string): string, normaliser?: callable(string): string}>
 */
#[AsCommand(name: 'app:create', description: 'Creates an app skeleton')]
#[Package('core')]
class CreateAppCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractAppLifecycle $appLifecycle,
        private readonly string $appDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        foreach (self::getPropertyDefinitions() as $property) {
            $this->addArgument($property['name'], InputArgument::OPTIONAL, $property['description']);
        }

        $this->addOption('theme', 't', InputOption::VALUE_NONE, 'Create a theme configuration file');
        $this->addOption('install', 'i', InputOption::VALUE_NONE, 'Install the application');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $propertyDefinitions = self::getPropertyDefinitions();

        $details = $this->gatherDetails($propertyDefinitions, $io, $input);

        try {
            $this->validateDetails($details, $propertyDefinitions);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $details = $this->normalizeDetails($details, $propertyDefinitions);

        $io->info('Creating app structure under ' . $details['name']);
        $dir = $this->appDir . '/' . $details['name'];

        try {
            $this->createApp($dir, $details, $input->getOption('theme'));
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $doInstall = $input->getOption('install');

        if (!$doInstall && $input->isInteractive()) {
            $question = new ConfirmationQuestion('Would you like to install your app? ', false);
            $doInstall = $io->askQuestion($question);
        }

        if ($doInstall) {
            $this->appLifecycle->install(
                Manifest::createFromXmlFile($dir . '/manifest.xml'),
                true,
                Context::createDefaultContext()
            );

            $io->success(sprintf('App %s has been successfully installed.', $details['name']));
        }

        return self::SUCCESS;
    }

    /**
     * @param PropertyDefinitions $propertyDefinitions
     *
     * @return array<string, string>
     */
    private function gatherDetails(array $propertyDefinitions, ShopwareStyle $io, InputInterface $input): array
    {
        return array_map(
            function (array $property) use ($io, $input) {
                if ($input->getArgument($property['name']) !== null) {
                    return $input->getArgument($property['name']);
                }

                $question = new Question($property['prompt'] . ': ', $property['default']);
                $question->setValidator($property['validator'] ?? null);
                $question->setMaxAttempts(2);

                return $io->askQuestion($question);
            },
            $propertyDefinitions
        );
    }

    /**
     * @param array<string, string> $details
     * @param PropertyDefinitions $propertyDefinitions
     */
    private function validateDetails(array $details, array $propertyDefinitions): void
    {
        foreach ($propertyDefinitions as $property) {
            if (!isset($property['validator'])) {
                continue;
            }

            $property['validator']($details[$property['name']]);
        }
    }

    /**
     * @return callable(string): string
     */
    private static function makeRegexValidator(string $regex, string $message): callable
    {
        return static function (string $value) use ($regex, $message): string {
            if (preg_match($regex, $value) !== 1) {
                throw new \RuntimeException($message);
            }

            return $value;
        };
    }

    /**
     * @return PropertyDefinitions
     */
    private static function getPropertyDefinitions(): array
    {
        $properties = [
            [
                'name' => 'name',
                'prompt' => 'Please enter a name for your app',
                'description' => 'The name of your app. Used for the folder structure.',
                'default' => 'MyExampleApp',
                'validator' => self::makeRegexValidator(
                    '/^[A-Za-z]\w{3,}$/',
                    'The app name is too short (min 4 characters), contains invalid characters'
                ),
                'normaliser' => fn (string $name): string => u($name)->replace('_', ' ')->camel()->title()->toString(),
            ],
            [
                'name' => 'label',
                'prompt' => 'Please enter a label for your app',
                'description' => 'The label for your app.',
                'default' => 'My Example App',
                'validator' => self::makeRegexValidator(
                    '/[\w\s]+$/',
                    'The app label contains invalid characters. Only alphanumerics and whitespaces are allowed.'
                ),
            ],
            [
                'name' => 'description',
                'prompt' => 'Please enter a description for your app',
                'description' => 'The description for your app.',
                'default' => 'A description',
                'validator' => self::makeRegexValidator(
                    '/[\w\s]+$/',
                    'The app description contains invalid characters. Only alphanumerics and whitespaces are allowed.'
                ),
            ],
            [
                'name' => 'author',
                'prompt' => 'Please enter the app author',
                'description' => 'The author of your app, name, company, etc',
                'default' => 'Your Company Ltd.',
            ],
            [
                'name' => 'copyright',
                'prompt' => 'Please enter your company copyright',
                'description' => 'The copyright for your app.',
                'default' => '(c) by Your Company Ltd.',
            ],
            [
                'name' => 'version',
                'prompt' => 'Please enter the version of your app',
                'description' => 'The version of your app.',
                'default' => '1.0.0',
                'validator' => self::makeRegexValidator('/^\d+\.\d+\.\d+$/', 'App version must be a valid Semver string.'),
            ],
            [
                'name' => 'icon',
                'prompt' => 'Please enter the relative path to your app icon',
                'description' => 'The path to your app icon.',
                'default' => '',
            ],
            [
                'name' => 'license',
                'prompt' => 'Please enter the app license',
                'description' => 'The license your app is using.',
                'default' => 'MIT',
            ],
        ];

        return array_combine(
            array_map(fn (array $property) => $property['name'], $properties),
            $properties
        );
    }

    /**
     * @param array<string, string> $details
     * @param PropertyDefinitions $propertyDefinitions
     *
     * @return array<string, string>
     */
    private function normalizeDetails(array $details, array $propertyDefinitions): array
    {
        return array_combine(
            array_keys($details),
            array_map(
                fn (string $propertyName, string $value) => isset($propertyDefinitions[$propertyName]['normaliser'])
                    ? $propertyDefinitions[$propertyName]['normaliser']($value)
                    : $value,
                array_keys($details),
                $details
            )
        );
    }

    private function getManifestTemplate(): string
    {
        return <<<EOL
        <?xml version="1.0" encoding="UTF-8"?>
        <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/Framework/App/Manifest/Schema/manifest-2.0.xsd">
            <meta>
                <name>{{name}}</name>
                <label>{{label}}</label>
                <description>{{description}}</description>
                <author>{{author}}</author>
                <copyright>{{copyright}}</copyright>
                <version>{{version}}</version>
                <icon>{{icon}}</icon>
                <license>{{license}}</license>
            </meta>
        </manifest>
        EOL;
    }

    private function getThemeConfigTemplate(): string
    {
        return <<<EOL
        {
          "name": "{{name}}",
          "author": "{{author}}",
          "views": [
             "@Storefront",
             "@Plugins",
             "@{{name}}"
          ],
          "style": [
            "app/storefront/src/scss/overrides.scss",
            "@Storefront",
            "app/storefront/src/scss/base.scss"
          ],
          "script": [
            "@Storefront",
            "app/storefront/dist/storefront/js/{{name-snake-case}}.js"
          ],
          "asset": [
            "@Storefront",
            "app/storefront/src/assets"
          ]
        }
        EOL;
    }

    /**
     * @param array<string, string> $details
     */
    private function createApp(string $appDirectory, array $details, bool $createThemeConfig): void
    {
        if (file_exists($appDirectory)) {
            throw new \RuntimeException(sprintf('App directory %s already exists', $details['name']));
        }

        $manifestContent = $this->replaceTemplateValues(
            $this->getManifestTemplate(),
            $details
        );

        $this->createDirectory($appDirectory);

        file_put_contents($appDirectory . '/manifest.xml', $manifestContent);

        if ($createThemeConfig) {
            $manifestContent = $this->replaceTemplateValues(
                $this->getThemeConfigTemplate(),
                [
                    'name' => $details['name'],
                    'author' => $details['author'],
                    'name-snake-case' => (new CamelCaseToSnakeCaseNameConverter())->normalize($details['name']),
                ]
            );

            $this->createDirectory($appDirectory . '/Resources');

            file_put_contents($appDirectory . '/Resources/theme.json', $manifestContent);
        }
    }

    private function createDirectory(string $pathName): void
    {
        if (!mkdir($pathName, 0755, true) && !is_dir($pathName)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s". Please check permissions', $pathName));
        }
    }

    /**
     * @param array<string, string> $details
     */
    private function replaceTemplateValues(string $manifestTemplate, array $details): string
    {
        return str_replace(
            array_map(fn ($param) => sprintf('{{%s}}', $param), array_keys($details)),
            array_values($details),
            $manifestTemplate
        );
    }
}
