<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use function Flag\next739;
use function Flag\next754;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Configuration\ConfigurationGroupDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Attribute\AttributeSetDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Demodata\Generator\ProductGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DemodataCommand extends Command
{
    /**
     * @var DemodataService
     */
    private $demodataService;

    /**
     * @var string
     */
    private $kernelEnv;

    public function __construct(DemodataService $demodataService, string $kernelEnv)
    {
        parent::__construct();
        $this->kernelEnv = $kernelEnv;
        $this->demodataService = $demodataService;
    }

    protected function configure(): void
    {
        $this->setName('framework:demodata');
        $this->addOption('products', 'p', InputOption::VALUE_REQUIRED, 'Product count', 60);
        $this->addOption('categories', 'c', InputOption::VALUE_REQUIRED, 'Category count', 7);
        $this->addOption('orders', 'o', InputOption::VALUE_REQUIRED, 'Order count', 60);
        $this->addOption('manufacturers', 'm', InputOption::VALUE_REQUIRED, 'Manufacturer count', 60);
        $this->addOption('customers', 'cs', InputOption::VALUE_REQUIRED, 'Customer count', 60);
        $this->addOption('media', '', InputOption::VALUE_REQUIRED, 'Media count', 100);
        $this->addOption('properties', '', InputOption::VALUE_REQUIRED, 'Property group count (option count rand(30-300))', 10);

        if (next739()) {
            $this->addOption('product-streams', 'ps', InputOption::VALUE_REQUIRED, 'Product streams count', 10);
        }

        $this->addOption('with-configurator', 'w', InputOption::VALUE_OPTIONAL, 'Enables configurator products', 0);
        $this->addOption('with-services', 'x', InputOption::VALUE_OPTIONAL, 'Enables services for products', 1);
        $this->addOption('with-media', 'y', InputOption::VALUE_OPTIONAL, 'Enables media for products', 1);

        if (next754()) {
            $this->addOption('attribute-sets', null, InputOption::VALUE_REQUIRED, 'Attribute set count', 4);

            $this->addOption('product-attributes', null, InputOption::VALUE_REQUIRED, 'Products attribute count');
            $this->addOption('manufacturer-attributes', null, InputOption::VALUE_REQUIRED, 'Manufacturer attribute count');
            $this->addOption('category-attributes', null, InputOption::VALUE_REQUIRED, 'Category attribute count');
            $this->addOption('order-attributes', null, InputOption::VALUE_REQUIRED, 'Order attribute count');
            $this->addOption('customer-attributes', null, InputOption::VALUE_REQUIRED, 'Customer attribute count');
            $this->addOption('media-attributes', null, InputOption::VALUE_REQUIRED, 'Media attribute count');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->kernelEnv !== 'prod') {
            $output->writeln('Demo data command should only be used in production environment. You can provide the environment as follow `APP_ENV=prod framework:demodata`');

            return null;
        }

        $io = new SymfonyStyle($input, $output);
        $io->title('Demodata Generator');

        $context = Context::createDefaultContext();

        $request = new DemodataRequest();

        $request->add(RuleDefinition::class, 5);
        $request->add(CustomerDefinition::class, (int) $input->getOption('customers'));
        $request->add(ConfigurationGroupDefinition::class, (int) $input->getOption('properties'));
        $request->add(ShippingMethodPriceDefinition::class, 1);
        $request->add(CategoryDefinition::class, (int) $input->getOption('categories'));
        $request->add(ProductManufacturerDefinition::class, (int) $input->getOption('manufacturers'));
        $request->add(ProductDefinition::class, (int) $input->getOption('products'), $this->getProductOptions($input));

        if (next739()) {
            $request->add(ProductStreamDefinition::class, (int) $input->getOption('product-streams'));
        }

        $request->add(OrderDefinition::class, (int) $input->getOption('orders'));
        $request->add(MediaDefinition::class, (int) $input->getOption('media'));
        $request->add(CmsPageDefinition::class, 1);

        if (next754()) {
            $request->add(
                AttributeSetDefinition::class,
                (int) $input->getOption('attribute-sets'),
                $this->getAttributeOptions($input)
            );
        }

        $demoContext = $this->demodataService->generate($request, $context, $io);

        $io->table(
            ['Entity', 'Items', 'Time'],
            $demoContext->getTimings()
        );
    }

    protected function getProductOptions(InputInterface $input): array
    {
        $productOptions = [];

        if ($input->getOption('with-media')) {
            $productOptions[ProductGenerator::OPTIONS_WITH_MEDIA] = true;
        }

        if ($input->getOption('with-configurator')) {
            $productOptions[ProductGenerator::OPTIONS_WITH_CONFIGURATOR] = true;
        }

        if ($input->getOption('with-services')) {
            $productOptions[ProductGenerator::OPTIONS_WITH_SERVICES] = true;
        }

        return $productOptions;
    }

    protected function getAttributeOptions(InputInterface $input): array
    {
        return [
            'relations' => [
                'product' => (int) ($input->getOption('product-attributes') ?? $input->getOption('product-attributes') * 0.1),
                'product_manufacturer' => (int) ($input->getOption('manufacturer-attributes') ?? $input->getOption('manufacturer-attributes') * 0.1),
                'category' => (int) ($input->getOption('category-attributes') ?? $input->getOption('category-attributes') * 0.1),
                'order' => (int) ($input->getOption('order-attributes') ?? $input->getOption('order-attributes') * 0.1),
                'customer' => (int) ($input->getOption('customer-attributes') ?? $input->getOption('customer-attributes') * 0.1),
                'media' => (int) ($input->getOption('media-attributes') ?? $input->getOption('media-attributes') * 0.1),
            ],
        ];
    }
}
