<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Command;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Flow\FlowDefinition;
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
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DemodataCommand extends Command
{
    private const DEFAULT_COUNTS = [
        'products' => 1000,
        'promotions' => 50,
        'categories' => 10,
        'orders' => 60,
        'manufacturers' => 60,
        'customers' => 60,
        'media' => 300,
        'properties' => 10,
        'users' => 0,
        'product-streams' => 10,
        'mail-template' => 10,
        'mail-header-footer' => 3,
        'reviews' => 20,
        'attribute-sets' => 4,
        'flows' => 0,
        'rules' => 25,
        'tags' => 50,
    ];

    protected static $defaultName = 'framework:demodata';

    private DemodataService $demodataService;

    private string $kernelEnv;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @internal
     */
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
        $this->addOption('products', 'p', InputOption::VALUE_REQUIRED, 'Product count');
        $this->addOption('promotions', 'pr', InputOption::VALUE_REQUIRED, 'Promotion count');
        $this->addOption('categories', 'c', InputOption::VALUE_REQUIRED, 'Category count');
        $this->addOption('orders', 'o', InputOption::VALUE_REQUIRED, 'Order count');
        $this->addOption('manufacturers', 'm', InputOption::VALUE_REQUIRED, 'Manufacturer count');
        $this->addOption('customers', 'cs', InputOption::VALUE_REQUIRED, 'Customer count');
        $this->addOption('media', '', InputOption::VALUE_REQUIRED, 'Media count');
        $this->addOption('properties', '', InputOption::VALUE_REQUIRED, 'Property group count (option count rand(30-300))');
        $this->addOption('users', '', InputOption::VALUE_REQUIRED, 'Users count');

        $this->addOption('product-streams', 'ps', InputOption::VALUE_REQUIRED, 'Product streams count');

        $this->addOption('mail-template', 'mt', InputOption::VALUE_REQUIRED, 'Mail template count');
        $this->addOption('mail-header-footer', 'mhf', InputOption::VALUE_REQUIRED, 'Mail header/footer count');
        $this->addOption('with-media', 'y', InputOption::VALUE_OPTIONAL, 'Enables media for products', '1');
        $this->addOption('reviews', 'r', InputOption::VALUE_OPTIONAL, 'Reviews count');

        $this->addOption('attribute-sets', null, InputOption::VALUE_REQUIRED, 'CustomField set count');
        $this->addOption('product-attributes', null, InputOption::VALUE_REQUIRED, 'Products attribute count');
        $this->addOption('manufacturer-attributes', null, InputOption::VALUE_REQUIRED, 'Manufacturer attribute count');
        $this->addOption('order-attributes', null, InputOption::VALUE_REQUIRED, 'Order attribute count');
        $this->addOption('customer-attributes', null, InputOption::VALUE_REQUIRED, 'Customer attribute count');
        $this->addOption('media-attributes', null, InputOption::VALUE_REQUIRED, 'Media attribute count');
        $this->addOption('flows', 'fl', InputOption::VALUE_OPTIONAL, 'Flows count');
        $this->addOption('rules', 'R', InputOption::VALUE_OPTIONAL, 'Rules count');
        $this->addOption('tags', null, InputOption::VALUE_OPTIONAL, 'Tags count');

        $this->addOption('reset-defaults', null, InputOption::VALUE_NONE, 'Set all counts to 0 unless specified');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->kernelEnv !== 'prod') {
            $output->writeln('Demo data command should only be used in production environment. You can provide the environment as follow `APP_ENV=prod framework:demodata`');

            return self::SUCCESS;
        }

        $io = new ShopwareStyle($input, $output);
        $io->title('Demodata Generator');

        $context = Context::createDefaultContext();

        $request = new DemodataRequest();

        $request->add(TagDefinition::class, $this->getCount($input, 'tags'));
        $request->add(RuleDefinition::class, $this->getCount($input, 'rules'));
        $request->add(MediaDefinition::class, $this->getCount($input, 'media'));
        $request->add(CustomerDefinition::class, $this->getCount($input, 'customers'));
        $request->add(PropertyGroupDefinition::class, $this->getCount($input, 'properties'));
        $request->add(CategoryDefinition::class, $this->getCount($input, 'categories'));
        $request->add(ProductManufacturerDefinition::class, $this->getCount($input, 'manufacturers'));
        $request->add(ProductDefinition::class, $this->getCount($input, 'products'));
        $request->add(ProductStreamDefinition::class, $this->getCount($input, 'product-streams'));
        $request->add(PromotionDefinition::class, $this->getCount($input, 'promotions'));
        $request->add(OrderDefinition::class, $this->getCount($input, 'orders'));
        $request->add(ProductReviewDefinition::class, $this->getCount($input, 'reviews'));
        $request->add(UserDefinition::class, $this->getCount($input, 'users'));
        $request->add(FlowDefinition::class, $this->getCount($input, 'flows'));

        $request->add(
            CustomFieldSetDefinition::class,
            $this->getCount($input, 'attribute-sets'),
            $this->getCustomFieldOptions($input)
        );

        $request->add(MailTemplateDefinition::class, $this->getCount($input, 'mail-template'));
        $request->add(MailHeaderFooterDefinition::class, $this->getCount($input, 'mail-header-footer'));

        $this->eventDispatcher->dispatch(new DemodataRequestCreatedEvent($request, $context));

        $demoContext = $this->demodataService->generate($request, $context, $io);

        $io->table(
            ['Entity', 'Items', 'Time'],
            $demoContext->getTimings()
        );

        return self::SUCCESS;
    }

    private function getCustomFieldOptions(InputInterface $input): array
    {
        return [
            'relations' => [
                'product' => ($input->getOption('product-attributes') ?? (int) $input->getOption('product-attributes') * 0.1),
                'product_manufacturer' => ($input->getOption('manufacturer-attributes') ?? (int) $input->getOption('manufacturer-attributes') * 0.1),
                'order' => ($input->getOption('order-attributes') ?? (int) $input->getOption('order-attributes') * 0.1),
                'customer' => ($input->getOption('customer-attributes') ?? (int) $input->getOption('customer-attributes') * 0.1),
                'media' => ($input->getOption('media-attributes') ?? (int) $input->getOption('media-attributes') * 0.1),
            ],
        ];
    }

    private function getCount(InputInterface $input, string $name): int
    {
        if ($input->getOption($name) !== null) {
            return (int) $input->getOption($name);
        }

        if ($input->getOption('reset-defaults')) {
            return 0;
        }

        return self::DEFAULT_COUNTS[$name];
    }
}
