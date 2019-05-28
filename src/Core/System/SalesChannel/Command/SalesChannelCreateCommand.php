<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Uuid\Uuid;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SalesChannelCreateCommand extends Command implements CompletionAwareInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $shippingMethodRepository;
    /**
     * @var EntityRepositoryInterface
     */
    private $countryRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $snippetSetRepository;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerGroupRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $snippetSetRepository,
        EntityRepositoryInterface $customerGroupRepository,
        EntityRepositoryInterface $currencyRepository
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->countryRepository = $countryRepository;
        $this->snippetSetRepository = $snippetSetRepository;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->currencyRepository = $currencyRepository;

        parent::__construct();
    }

    public function completeOptionValues($optionName, CompletionContext $context)
    {
        if ($optionName === 'languageId') {
        } elseif ($optionName === 'snippetSetId') {
            return $this->snippetSetRepository->searchIds(new Criteria(), Context::createDefaultContext())->getIds();
        } elseif ($optionName === 'currencyId') {
            return $this->currencyRepository->searchIds(new Criteria(), Context::createDefaultContext())->getIds();
        } elseif ($optionName === 'paymentMethodId') {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('active', true));

            return $this->paymentMethodRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        } elseif ($optionName === 'shippingMethodId') {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('active', true));

            return $this->shippingMethodRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        } elseif ($optionName === 'countryId') {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('active', true));

            return $this->countryRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        } elseif ($optionName === 'typeId') {
            return [
                Defaults::SALES_CHANNEL_TYPE_API,
                Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            ];
        } elseif ($optionName === 'customerGroupId') {
            return $this->customerGroupRepository->searchIds(new Criteria(), Context::createDefaultContext())->getIds();
        }

        return [];
    }

    public function completeArgumentValues($argumentName, CompletionContext $context)
    {
        return [];
    }

    protected function configure(): void
    {
        $this->setName('sales-channel:create')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Id for the sales channel', Uuid::randomHex())
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name for the application')
            ->addOption('languageId', null, InputOption::VALUE_REQUIRED, 'Default language', Defaults::LANGUAGE_SYSTEM)
            ->addOption('snippetSetId', null, InputOption::VALUE_REQUIRED, 'Default snippet set')
            ->addOption('currencyId', null, InputOption::VALUE_REQUIRED, 'Default currency', Defaults::CURRENCY)
            ->addOption('paymentMethodId', null, InputOption::VALUE_REQUIRED, 'Default payment method')
            ->addOption('shippingMethodId', null, InputOption::VALUE_REQUIRED, 'Default shipping method')
            ->addOption('countryId', null, InputOption::VALUE_REQUIRED, 'Default country')
            ->addOption('typeId', null, InputOption::VALUE_OPTIONAL, 'Sales channel type id')
            ->addOption('customerGroupId', null, InputOption::VALUE_REQUIRED, 'Default customer group', Defaults::FALLBACK_CUSTOMER_GROUP)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getOption('id');
        $typeId = $input->getOption('typeId');

        $io = new ShopwareStyle($input, $output);

        $paymentMethod = $input->getOption('paymentMethodId') ?? $this->getFirstActivePaymentMethodId();
        $shippingMethod = $input->getOption('shippingMethodId') ?? $this->getFirstActiveShippingMethodId();
        $countryId = $input->getOption('countryId') ?? $this->getFirstActiveCountryId();
        $snippetSet = $input->getOption('snippetSetId') ?? $this->getSnippetSetId();
        $context = Context::createDefaultContext();

        $data = [
            'id' => $id,
            'name' => $input->getOption('name') ?? 'Headless',
            'typeId' => $typeId ?? $this->getTypeId(),
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),

            // default selection
            'languageId' => $input->getOption('languageId'),
            'snippetSetId' => $snippetSet,
            'currencyId' => $input->getOption('currencyId'),
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $paymentMethod,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $shippingMethod,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $countryId,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'customerGroupId' => $input->getOption('customerGroupId'),

            // available mappings
            'currencies' => $this->getAllIdsOf('currency', $context),
            'languages' => $this->getAllIdsOf('language', $context),
            'shippingMethods' => $this->getAllIdsOf('shipping_method', $context),
            'paymentMethods' => $this->getAllIdsOf('payment_method', $context),
            'countries' => $this->getAllIdsOf('country', $context),
        ];

        $data = array_replace_recursive($data, $this->getSalesChannelConfiguration($input, $output));

        try {
            $this->salesChannelRepository->create([$data], Context::createDefaultContext());

            $io->success('Sales channel has been created successfully.');
        } catch (WriteStackException $exception) {
            $io->error('Something went wrong.');

            $messages = [];
            foreach ($exception->getExceptions() as $err) {
                if ($err instanceof InvalidFieldException) {
                    foreach ($err->getViolations() as $violation) {
                        $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
                    }
                }
            }

            $io->listing($messages);

            return null;
        }

        $io->text('Access tokens:');

        $table = new Table($output);
        $table->setHeaders(['Key', 'Value']);

        $table->addRows([
            ['Access key', $data['accessKey']],
        ]);

        $table->render();
    }

    protected function getSalesChannelConfiguration(InputInterface $input, OutputInterface $output): array
    {
        return [];
    }

    protected function getTypeId(): string
    {
        return Defaults::SALES_CHANNEL_TYPE_API;
    }

    protected function getFirstActiveShippingMethodId(): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true));

        return $this->shippingMethodRepository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    protected function getFirstActivePaymentMethodId(): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position'));

        return $this->paymentMethodRepository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    protected function getFirstActiveCountryId(): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position'));

        return $this->countryRepository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    protected function getSnippetSetId(): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('iso', 'en-GB'));

        $id = $this->snippetSetRepository->searchIds($criteria, Context::createDefaultContext())->getIds()[0] ?? null;

        if ($id === null) {
            throw new \InvalidArgumentException('Unable to get default SnippetSet. Please provide a valid SnippetSetId.');
        }

        return $id;
    }

    private function getAllIdsOf(string $entity, Context $context): array
    {
        $repository = $this->definitionRegistry->getRepository($entity);

        return array_map(
            function (string $id) {
                return ['id' => $id];
            },
            $repository->searchIds(new Criteria(), $context)->getIds()
        );
    }
}
