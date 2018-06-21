<?php declare(strict_types=1);

namespace Shopware\Storefront\Command;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\Touchpoint\TouchpointRepository;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\ConstraintViolation;

class TouchpointCreateStorefrontCommand extends ContainerAwareCommand
{
    /**
     * @var
     */
    private $touchpointRepository;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(TouchpointRepository $touchpointRepository, Connection $connection)
    {
        parent::__construct();

        $this->touchpointRepository = $touchpointRepository;
        $this->connection = $connection;
    }

    protected function configure()
    {
        $this->setName('touchpoint:create:storefront')
            ->addArgument('storefront_url', InputArgument::REQUIRED, 'URL to public folder of the application')
            ->addOption('tenant-id', 't', InputOption::VALUE_REQUIRED, 'Tenant id')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name for the application', 'Storefront application')
            ->addOption('languageId', null, InputOption::VALUE_REQUIRED, 'Default language', Defaults::LANGUAGE)
            ->addOption('currencyId', null, InputOption::VALUE_REQUIRED, 'Default currency', Defaults::CURRENCY)
            ->addOption('paymentMethodId', null, InputOption::VALUE_REQUIRED, 'Default payment method', Defaults::PAYMENT_METHOD_DEBIT)
            ->addOption('shippingMethodId', null, InputOption::VALUE_REQUIRED, 'Default shipping method', Defaults::SHIPPING_METHOD)
            ->addOption('countryId', null, InputOption::VALUE_REQUIRED, 'Default country', Defaults::COUNTRY)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tenantId = $input->getOption('tenant-id');

        if (!$tenantId) {
            throw new \Exception('No tenant id provided');
        }
        if (!Uuid::isValid($tenantId)) {
            throw new \Exception('Invalid uuid provided');
        }

        $io = new SymfonyStyle($input, $output);

        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'type' => 'storefront',
            'accessKey' => str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(Random::getAlphanumericString(32))),
            'secretAccessKey' => str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(Random::getAlphanumericString(52))),
            'configuration' => [
                'domains' => [
                    ['url' => $input->getArgument('storefront_url'), 'language' => $input->getOption('languageId')],
                ],
            ],
            'languageId' => $input->getOption('languageId'),
            'currencyId' => $input->getOption('currencyId'),
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $input->getOption('paymentMethodId'),
            'paymentMethodVersionI' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $input->getOption('shippingMethodId'),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $input->getOption('countryId'),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'catalogIds' => [Defaults::CATALOG],
            'currencyIds' => [$input->getOption('currencyId')],
            'languageIds' => [Defaults::LANGUAGE],
            'name' => $input->getOption('name'),
        ];

        $idBin = Uuid::fromHexToBytes($id);

        try {
            $this->touchpointRepository->create([$data], Context::createDefaultContext($tenantId));

            $io->success('Touchpoint has been created successfully.');
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
            ['Secret access key', $data['secretAccessKey']],
        ]);

        $table->render();
    }
}
