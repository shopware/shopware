<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Console;

use Pimple\Container;
use Shopware\Recovery\Install\Command\InstallCommand;
use Shopware\Recovery\Install\ContainerProvider;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\HttpKernel\KernelInterface;

class Application extends BaseApplication
{
    private Container $container;

    public function __construct(string $env, KernelInterface $kernel)
    {
        $this->registerErrorHandler();

        parent::__construct('Shopware Installer', '1.0.0');

        $kernel->boot();

        $config = require __DIR__ . '/../../config/' . $env . '.php';
        $this->container = new Container();
        $this->container->offsetSet('shopware.kernel', $kernel);
        $this->container->register(new ContainerProvider($config));

        $this->getDefinition()->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', $env));
    }

    /**
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Overridden so that the application doesn't expect the command
     * name to be the first argument.
     */
    public function getDefinition()
    {
        $inputDefinition = parent::getDefinition();
        // clear out the normal first argument, which is the command name
        $inputDefinition->setArguments();

        return $inputDefinition;
    }

    /**
     * Gets the name of the command based on input.
     *
     * @param InputInterface $input The input interface
     *
     * @return string The command name
     */
    protected function getCommandName(InputInterface $input)
    {
        // This should return the name of your command.
        return 'install';
    }

    /**
     * Gets the default commands that should always be available.
     *
     * @return array An array of default Command instances
     */
    protected function getDefaultCommands()
    {
        // Keep the core default commands to have the HelpCommand
        // which is used when using the --help option
        $defaultCommands = parent::getDefaultCommands();

        $defaultCommands[] = new InstallCommand();

        return $defaultCommands;
    }

    private function registerErrorHandler(): void
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext) {
            // error was suppressed with the @-operator
            if (error_reporting() === 0) {
                return false;
            }

            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
    }
}
