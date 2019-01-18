<?php declare(strict_types=1);

namespace SwagExample\Command;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CustomerPrintCommand extends Command
{
    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    public function __construct(RepositoryInterface $customerRepository, $name = null)
    {
        parent::__construct($name);
        $this->customerRepository = $customerRepository;
    }

    protected function configure(): void
    {
        $this
            ->setName('customers:print')
            ->setDescription('Prints out all customers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        /** @var CustomerEntity[] $customers */
        $customers = $this->customerRepository->search(new Criteria(), Context::createDefaultContext())->getElements();

        foreach ($customers as $customer) {
            $output->writeln($customer->getLastName() . ', ' . $customer->getFirstName());
        }
    }
}
