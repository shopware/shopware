<?php declare(strict_types=1);

namespace Shopware\Core\System\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\ConstraintViolation;

class SalesChannelCreateCommand extends ContainerAwareCommand
{
    /**
     * @var RepositoryInterface
     */
    private $salesChannelRepository;

    public function __construct(RepositoryInterface $salesChannelRepository)
    {
        parent::__construct();

        $this->salesChannelRepository = $salesChannelRepository;
    }

    protected function configure()
    {
        $this->setName('sales-channel:create')
            ->addOption('tenant-id', 't', InputOption::VALUE_REQUIRED, 'Tenant id')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Id for the sales channel', Uuid::uuid4()->getHex())
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name for the application', 'Storefront API endpoint')
            ->addOption('languageId', null, InputOption::VALUE_REQUIRED, 'Default language', Defaults::LANGUAGE)
            ->addOption('currencyId', null, InputOption::VALUE_REQUIRED, 'Default currency', Defaults::CURRENCY)
            ->addOption('paymentMethodId', null, InputOption::VALUE_REQUIRED, 'Default payment method', Defaults::PAYMENT_METHOD_DEBIT)
            ->addOption('shippingMethodId', null, InputOption::VALUE_REQUIRED, 'Default shipping method', Defaults::SHIPPING_METHOD)
            ->addOption('countryId', null, InputOption::VALUE_REQUIRED, 'Default country', Defaults::COUNTRY)
            ->addOption('typeId', null, InputOption::VALUE_OPTIONAL, 'Sales channel type id')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tenantId = $input->getOption('tenant-id');
        $id = $input->getOption('id');
        $typeId = $input->getOption('typeId');

        if (!$tenantId) {
            throw new \Exception('No tenant id provided');
        }

        if (!Uuid::isValid($tenantId)) {
            throw new \Exception('Invalid uuid provided');
        }

        if (!Uuid::isValid($tenantId)) {
            throw new \Exception('Invalid uuid provided');
        }

        $io = new SymfonyStyle($input, $output);

        $data = [
            'id' => $id,
            'typeId' => $typeId ?? $this->getTypeId(),
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'configuration' => $this->getSalesChannelConfiguration($input, $output),
            'languageId' => $input->getOption('languageId'),
            'currencyId' => $input->getOption('currencyId'),
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $input->getOption('paymentMethodId'),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $input->getOption('shippingMethodId'),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $input->getOption('countryId'),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'catalogs' => [['id' => Defaults::CATALOG]],
            'currencies' => [['id' => $input->getOption('currencyId')]],
            'languages' => [['id' => Defaults::LANGUAGE]],
            'name' => $input->getOption('name'),
        ];

        try {
            $this->salesChannelRepository->create([$data], Context::createDefaultContext($tenantId));

            $io->success('Sales channel has been created successfully.');
        } catch (WriteStackException $exception) {
            $io->error('Something went wrong.');

            $messages = [];
            foreach ($exception->getExceptions() as $err) {
                /** @var ConstraintViolation $violation */
                foreach ($err->getViolations() as $violation) {
                    $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
                }
            }

            $io->listing($messages);

            return;
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
