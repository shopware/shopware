<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PluginCreateCommand extends Command
{
    private $composerTemplate = <<<EOL
{
  "name": "swag/plugin-skeleton",
  "description": "Skeleton plugin",
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
      "de-DE": "Skeleton plugin",
      "en-GB": "Skeleton plugin"
    }
  }
}

EOL;

    private $bootstrapTemplate = <<<EOL
<?php

namespace #namespace#;

use Shopware\Core\Framework\Plugin;

class #class# extends Plugin
{
}
EOL;

    private $servicesXmlTemplate = <<<EOL
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>

    </services>
</container>
EOL;

    /**
     * @var string
     */
    private $projectDir;

    public function __construct(string $projectDir)
    {
        parent::__construct();
        $this->projectDir = $projectDir;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('plugin:create')
            ->addArgument('name')
            ->setDescription('Creates a plugin skeleton');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        $name = ucfirst($name);

        $directory = $this->projectDir . '/custom/plugins/' . $name;

        if (file_exists($directory)) {
            throw new \RuntimeException(sprintf('Plugin directory %s already exists', $directory));
        }

        mkdir($directory . '/src/Resources/config/', 0777, true);

        $composerFile = $directory . '/composer.json';
        $bootstrapFile = $directory . '/src/' . $name . '.php';
        $servicesXmlFile = $directory . '/src/Resources/config/services.xml';

        $composer = str_replace(
            ['#namespace#', '#class#'],
            [$name, $name],
            $this->composerTemplate
        );

        $bootstrap = str_replace(
            ['#namespace#', '#class#'],
            [$name, $name],
            $this->bootstrapTemplate
        );

        file_put_contents($composerFile, $composer);
        file_put_contents($bootstrapFile, $bootstrap);
        file_put_contents($servicesXmlFile, $this->servicesXmlTemplate);
    }
}
