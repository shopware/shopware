<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SalesChannelCreateCommand extends Command
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

    public function __construct(
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $snippetSetRepository
    ) {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->countryRepository = $countryRepository;
        $this->snippetSetRepository = $snippetSetRepository;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('sales-channel:create')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Id for the sales channel', Uuid::randomHex())
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name for the application', 'SalesChannel API endpoint')
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

        $io = new SymfonyStyle($input, $output);

        $paymentMethod = $input->getOption('paymentMethodId') ?? $this->getFirstActivePaymentMethodId();
        $shippingMethod = $input->getOption('shippingMethodId') ?? $this->getFirstActiveShippingMethodId();
        $countryId = $input->getOption('countryId') ?? $this->getFirstActiveCountryId();
        $snippetSet = $input->getOption('snippetSetId') ?? $this->getSnippetSetId();

        $data = [
            'id' => $id,
            'typeId' => $typeId ?? $this->getTypeId(),
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
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
            'currencies' => [['id' => $input->getOption('currencyId')]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => $shippingMethod]],
            'paymentMethods' => [['id' => $paymentMethod]],
            'countries' => [['id' => $countryId]],
            'name' => $input->getOption('name'),
            'customerGroupId' => $input->getOption('customerGroupId'),
        ];
        $data = array_merge_recursive($data, $this->getSalesChannelConfiguration($input, $output));

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
            ->addFilter(new EqualsFilter('iso', 'en_GB'));

        $id = $this->snippetSetRepository->searchIds($criteria, Context::createDefaultContext())->getIds()[0] ?? null;

        if ($id === null) {
            throw new \InvalidArgumentException('Unable to get default SnippetSet. Please provide a valid SnippetSetId.');
        }

        return $id;
    }
}
