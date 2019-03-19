<?php declare(strict_types=1);

namespace Shopware\Core\System\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Struct\Uuid;
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

    public function __construct(EntityRepositoryInterface $salesChannelRepository)
    {
        parent::__construct();

        $this->salesChannelRepository = $salesChannelRepository;
    }

    protected function configure(): void
    {
        $this->setName('sales-channel:create')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Id for the sales channel', Uuid::uuid4()->getHex())
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name for the application', 'Storefront API endpoint')
            ->addOption('languageId', null, InputOption::VALUE_REQUIRED, 'Default language', Defaults::LANGUAGE_SYSTEM)
            ->addOption('snippetSetId', null, InputOption::VALUE_REQUIRED, 'Default snippet set', Defaults::SNIPPET_BASE_SET_EN)
            ->addOption('currencyId', null, InputOption::VALUE_REQUIRED, 'Default currency', Defaults::CURRENCY)
            ->addOption('paymentMethodId', null, InputOption::VALUE_REQUIRED, 'Default payment method', Defaults::PAYMENT_METHOD_DEBIT)
            ->addOption('shippingMethodId', null, InputOption::VALUE_REQUIRED, 'Default shipping method', Defaults::SHIPPING_METHOD)
            ->addOption('countryId', null, InputOption::VALUE_REQUIRED, 'Default country', Defaults::COUNTRY)
            ->addOption('typeId', null, InputOption::VALUE_OPTIONAL, 'Sales channel type id')
            ->addOption('customerGroupId', null, InputOption::VALUE_REQUIRED, 'Default customer group', Defaults::FALLBACK_CUSTOMER_GROUP)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getOption('id');
        $typeId = $input->getOption('typeId');

        $io = new SymfonyStyle($input, $output);

        $data = [
            'id' => $id,
            'typeId' => $typeId ?? $this->getTypeId(),
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => $input->getOption('languageId'),
            'snippetSetId' => $input->getOption('snippetSetId'),
            'currencyId' => $input->getOption('currencyId'),
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $input->getOption('paymentMethodId'),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $input->getOption('shippingMethodId'),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $input->getOption('countryId'),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => $input->getOption('currencyId')]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => $input->getOption('shippingMethodId')]],
            'paymentMethods' => [['id' => $input->getOption('paymentMethodId')]],
            'countries' => [['id' => $input->getOption('countryId')]],
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
        return Defaults::SALES_CHANNEL_STOREFRONT_API;
    }
}
