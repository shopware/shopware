<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Command;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Demodata\Event\DemodataRequestCreatedEvent;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DemodataCommand extends Command
{
    protected static $defaultName = 'framework:demodata';

    /**
     * @var DemodataService
     */
    private $demodataService;

    /**
     * @var string
     */
    private $kernelEnv;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        DemodataService $demodataService,
        EventDispatcherInterface $eventDispatcher,
        string $kernelEnv
    ) {
        parent::__construct();

        $this->kernelEnv = $kernelEnv;
        $this->demodataService = $demodataService;
        $this->eventDispatcher = $eventDispatcher;
    }

    protected function configure(): void
    {
        $this->addOption('products', 'p', InputOption::VALUE_REQUIRED, 'Product count', 60);
        $this->addOption('categories', 'c', InputOption::VALUE_REQUIRED, 'Category count', 7);
        $this->addOption('orders', 'o', InputOption::VALUE_REQUIRED, 'Order count', 60);
        $this->addOption('manufacturers', 'm', InputOption::VALUE_REQUIRED, 'Manufacturer count', 60);
        $this->addOption('customers', 'cs', InputOption::VALUE_REQUIRED, 'Customer count', 60);
        $this->addOption('media', '', InputOption::VALUE_REQUIRED, 'Media count', 100);
        $this->addOption('properties', '', InputOption::VALUE_REQUIRED, 'Property group count (option count rand(30-300))', 10);

        $this->addOption('product-streams', 'ps', InputOption::VALUE_REQUIRED, 'Product streams count', 10);

        $this->addOption('mail-template', 'mt', InputOption::VALUE_REQUIRED, 'Mail template count', 10);
        $this->addOption('mail-header-footer', 'mhf', InputOption::VALUE_REQUIRED, 'Mail header/footer count', 3);

        $this->addOption('with-media', 'y', InputOption::VALUE_OPTIONAL, 'Enables media for products', 1);

        $this->addOption('reviews', 'r', InputOption::VALUE_OPTIONAL, 'Reviews count', 20);

        $this->addOption('attribute-sets', null, InputOption::VALUE_REQUIRED, 'CustomField set count', 4);

        $this->addOption('product-attributes', null, InputOption::VALUE_REQUIRED, 'Products attribute count');
        $this->addOption('manufacturer-attributes', null, InputOption::VALUE_REQUIRED, 'Manufacturer attribute count');
        $this->addOption('order-attributes', null, InputOption::VALUE_REQUIRED, 'Order attribute count');
        $this->addOption('customer-attributes', null, InputOption::VALUE_REQUIRED, 'Customer attribute count');
        $this->addOption('media-attributes', null, InputOption::VALUE_REQUIRED, 'Media attribute count');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->kernelEnv !== 'prod') {
            $output->writeln('Demo data command should only be used in production environment. You can provide the environment as follow `APP_ENV=prod framework:demodata`');

            return 0;
        }

        $io = new ShopwareStyle($input, $output);
        $io->title('Demodata Generator');

        $context = Context::createDefaultContext();

        $request = new DemodataRequest();

        $request->add(RuleDefinition::class, 5);
        $request->add(MediaDefinition::class, (int) $input->getOption('media'));
        $request->add(CustomerDefinition::class, (int) $input->getOption('customers'));
        $request->add(PropertyGroupDefinition::class, (int) $input->getOption('properties'));
        $request->add(CategoryDefinition::class, (int) $input->getOption('categories'));
        $request->add(ProductManufacturerDefinition::class, (int) $input->getOption('manufacturers'));
        $request->add(ProductDefinition::class, (int) $input->getOption('products'));
        $request->add(ProductStreamDefinition::class, (int) $input->getOption('product-streams'));
        $request->add(OrderDefinition::class, (int) $input->getOption('orders'));
        $request->add(ProductReviewDefinition::class, (int) $input->getOption('reviews'));

        $request->add(
            CustomFieldSetDefinition::class,
            (int) $input->getOption('attribute-sets'),
            $this->getCustomFieldOptions($input)
        );

        $request->add(MailTemplateDefinition::class, (int) $input->getOption('mail-template'));
        $request->add(MailHeaderFooterDefinition::class, (int) $input->getOption('mail-header-footer'));

        $this->eventDispatcher->dispatch(new DemodataRequestCreatedEvent($request, $context));

        $demoContext = $this->demodataService->generate($request, $context, $io);

        $io->table(
            ['Entity', 'Items', 'Time'],
            $demoContext->getTimings()
        );

        return 0;
    }

    private function getCustomFieldOptions(InputInterface $input): array
    {
        return [
            'relations' => [
                'product' => (int) ($input->getOption('product-attributes') ?? $input->getOption('product-attributes') * 0.1),
                'product_manufacturer' => (int) ($input->getOption('manufacturer-attributes') ?? $input->getOption('manufacturer-attributes') * 0.1),
                'order' => (int) ($input->getOption('order-attributes') ?? $input->getOption('order-attributes') * 0.1),
                'customer' => (int) ($input->getOption('customer-attributes') ?? $input->getOption('customer-attributes') * 0.1),
                'media' => (int) ($input->getOption('media-attributes') ?? $input->getOption('media-attributes') * 0.1),
            ],
        ];
    }
}
