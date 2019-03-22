[titleEn]: <>(CLI Commands)
[titleDe]: <>(CLI Commands)
[wikiUrl]: <>(../plugin-system/cli-commands?category=shopware-platform-en/plugin-system)

Creating your own `CLI Commands` in Shopware is straightforward and goes analogous to [Symfony - Console Commands](https://symfony.com/doc/current/console.html).
In this guide, you are going to create a plugin, which introduces a new CLI command to print out all customers (last name, first name).
At the end of this guide, you can find the full example as download.


## Overview
```
└── plugins
    └── SwagExample
        ├── Command
        │   └── CustomerPrintCommand.php
        ├── DependencyInjection
        │   └── command.xml
        └── SwagExample.php
```
*Plugin file structure*

## Service Definition
In your service XML file you register the CLI command you are about to create:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

<services>
    <service id="SwagExample\Command\CustomerPrintCommand">
        <argument type="service" id="customer.repository"/>
        <tag name="console.command"/>
    </service>

</services>
</container>
```
*DependencyInjection/command.xml*

You define a new service `SwagPluginSystemCommandExample\CLI\CustomerCommand` and give it the tag `console.command`.
Because you want to print out all customers, you require the `customer.repository`.
Next load your service XML file in the plugin base class.

## Plugin Base Class
In your plugin base class you need to load your XML routing file: 

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class SwagExample extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('command.xml');
    }
}
```
*SwagExample.php*

## Command
In the command itself all you need to do is to configure it and implement the execute function:

```php
<?php declare(strict_types=1);

namespace SwagExample\Command;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CustomerPrintCommand extends Command
{
    /**
     * @var EntityRepositoryInterface 
     */
    private $customerRepository;

    public function __construct(RepositoryInterface $customerRepository, $name = null)
    {
        parent::__construct($name);
        $this->customerRepository = $customerRepository;
    }

    protected function configure(): void
    {
        $this
            ->setName('customers:print')
            ->setDescription('Prints out all customers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        /** @var CustomerEntity[] $customers */
        $customers = $this->customerRepository->search(new Criteria(), Context::createDefaultContext())->getElements();

        foreach ($customers as $customer) {
            $output->writeln($customer->getLastName() . ', ' . $customer->getFirstName());
        }
    }
}
```
*Command/CustomerPrintCommand.php*

In the constructor, you call the parent constructor and inject your dependency. In the configure function you 
configure the command, set the name, description, arguments and so on. The execute function is the function that gets called
when the command is running. Search for customers with an empty `Criteria` and the default `Context`.
Which results in every customer that's known to the `Repository`. Iterate over the customers and print out each.

## Download
Here you can *Download Link Here* the Plugin.
