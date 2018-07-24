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

class TouchpointCreateCommand extends ContainerAwareCommand
{
    /**
     * @var RepositoryInterface
     */
    private $touchpointRepository;

    public function __construct(RepositoryInterface $touchpointRepository)
    {
        parent::__construct();

        $this->touchpointRepository = $touchpointRepository;
    }

    protected function configure()
    {
        $this->setName('touchpoint:create')
            ->addOption('tenant-id', 't', InputOption::VALUE_REQUIRED, 'Tenant id')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Id for the touchpoint', Uuid::uuid4()->getHex())
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name for the application', 'Storefront API endpoint')
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
        $id = $input->getOption('id');

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

        $secretAccessKey = AccessKeyHelper::generateSecretAccessKey();

        $data = [
            'id' => $id,
            'type' => $this->getType(),
            'accessKey' => AccessKeyHelper::generateAccessKey('touchpoint'),
            'secretAccessKey' => $secretAccessKey,
            'configuration' => $this->getTouchpointConfiguration($input, $output),
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
            ['Secret access key', $secretAccessKey],
        ]);

        $table->render();
    }

    protected function getTouchpointConfiguration(InputInterface $input, OutputInterface $output): array
    {
        return [];
    }

    protected function getType(): string
    {
        return 'storefront_api';
    }
}
