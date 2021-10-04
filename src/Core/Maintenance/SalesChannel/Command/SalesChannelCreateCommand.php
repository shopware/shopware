<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\SalesChannel\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal should be used over the CLI only
 */
class SalesChannelCreateCommand extends Command
{
    protected static $defaultName = 'sales-channel:create';

    private EntityRepositoryInterface $paymentMethodRepository;

    private EntityRepositoryInterface $shippingMethodRepository;

    private EntityRepositoryInterface $countryRepository;

    private EntityRepositoryInterface $snippetSetRepository;

    private EntityRepositoryInterface $categoryRepository;

    private SalesChannelCreator $salesChannelCreator;

    public function __construct(
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $snippetSetRepository,
        EntityRepositoryInterface $categoryRepository,
        SalesChannelCreator $salesChannelCreator
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->countryRepository = $countryRepository;
        $this->snippetSetRepository = $snippetSetRepository;
        $this->categoryRepository = $categoryRepository;
        $this->salesChannelCreator = $salesChannelCreator;

        parent::__construct();
    }

    /**
     * @deprecated tag:v6.5.0 - Option `snippetSetId` will be removed as it had no effect, use `sales-channel:create:storefront` instead
     */
    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Id for the sales channel', Uuid::randomHex())
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name for the application')
            ->addOption('languageId', null, InputOption::VALUE_REQUIRED, 'Default language', Defaults::LANGUAGE_SYSTEM)
            ->addOption('snippetSetId', null, InputOption::VALUE_REQUIRED, 'Deprecated: will be removed in 6.5.0 as it had no effect')
            ->addOption('currencyId', null, InputOption::VALUE_REQUIRED, 'Default currency', Defaults::CURRENCY)
            ->addOption('paymentMethodId', null, InputOption::VALUE_REQUIRED, 'Default payment method')
            ->addOption('shippingMethodId', null, InputOption::VALUE_REQUIRED, 'Default shipping method')
            ->addOption('countryId', null, InputOption::VALUE_REQUIRED, 'Default country')
            ->addOption('typeId', null, InputOption::VALUE_OPTIONAL, 'Sales channel type id')
            ->addOption('customerGroupId', null, InputOption::VALUE_REQUIRED, 'Default customer group')
            ->addOption('navigationCategoryId', null, InputOption::VALUE_REQUIRED, 'Default Navigation Category')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $typeId = $input->getOption('typeId');

        $io = new ShopwareStyle($input, $output);

        try {
            $accessKey = $this->salesChannelCreator->createSalesChannel(
                $id,
                $input->getOption('name') ?? 'Headless',
                $typeId ?? $this->getTypeId(),
                $input->getOption('languageId'),
                $input->getOption('currencyId'),
                $input->getOption('snippetSetId'),
                $input->getOption('paymentMethodId'),
                $input->getOption('shippingMethodId'),
                $input->getOption('customerGroupId'),
                $input->getOption('navigationCategoryId'),
                null,
                null,
                null,
                null,
                null,
                $this->getSalesChannelConfiguration($input, $output)
            );

            $io->success('Sales channel has been created successfully.');
        } catch (WriteException $exception) {
            $io->error('Something went wrong.');

            $messages = [];
            foreach ($exception->getExceptions() as $err) {
                if ($err instanceof WriteConstraintViolationException) {
                    foreach ($err->getViolations() as $violation) {
                        $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
                    }
                }
            }

            $io->listing($messages);

            return self::SUCCESS;
        }

        $io->text('Access tokens:');

        $table = new Table($output);
        $table->setHeaders(['Key', 'Value']);

        $table->addRows([
            ['Access key', $accessKey],
        ]);

        $table->render();

        return self::SUCCESS;
    }

    protected function getSalesChannelConfiguration(InputInterface $input, OutputInterface $output): array
    {
        return [];
    }

    protected function getTypeId(): string
    {
        return Defaults::SALES_CHANNEL_TYPE_API;
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed, use SalesChannelCreator instead
     */
    protected function getFirstActiveShippingMethodId(): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true));

        /** @var string[] $ids */
        $ids = $this->shippingMethodRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return $ids[0];
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed, use SalesChannelCreator instead
     */
    protected function getFirstActivePaymentMethodId(): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position'));

        /** @var string[] $ids */
        $ids = $this->paymentMethodRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return $ids[0];
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed, use SalesChannelCreator instead
     */
    protected function getFirstActiveCountryId(): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position'));

        /** @var string[] $ids */
        $ids = $this->countryRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return $ids[0];
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed, use SalesChannelCreator instead
     */
    protected function getSnippetSetId(): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('iso', 'en-GB'));

        /** @var string|null $id */
        $id = $this->snippetSetRepository->searchIds($criteria, Context::createDefaultContext())->getIds()[0] ?? null;

        if ($id === null) {
            throw new \InvalidArgumentException('Unable to get default SnippetSet. Please provide a valid SnippetSetId.');
        }

        return $id;
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed, use SalesChannelCreator instead
     */
    protected function getRootCategoryId(): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('category.parentId', null));
        $criteria->addSorting(new FieldSorting('category.createdAt', FieldSorting::ASCENDING));

        /** @var string[] $categories */
        $categories = $this->categoryRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return $categories[0];
    }
}
