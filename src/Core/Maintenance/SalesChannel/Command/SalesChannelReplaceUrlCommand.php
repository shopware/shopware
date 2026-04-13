<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\SalesChannel\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[AsCommand(
    name: 'sales-channel:replace:url',
    description: 'Replaces the URL of a sales channel with a new URL',
)]
#[Package('discovery')]
class SalesChannelReplaceUrlCommand extends Command
{
    /**
     * @param EntityRepository<SalesChannelDomainCollection> $salesChannelDomainRepository
     */
    public function __construct(private readonly EntityRepository $salesChannelDomainRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('previous-url', InputArgument::REQUIRED, 'Previous URL of the sales channel');
        $this->addArgument('new-url', InputArgument::REQUIRED, 'New URL of the sales channel');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createCLIContext();
        $io = new SymfonyStyle($input, $output);

        $previousUrl = trim((string) $input->getArgument('previous-url'));
        $newUrl = trim((string) $input->getArgument('new-url'));

        if (!$this->validateUrls($previousUrl, $newUrl, $io)) {
            return self::FAILURE;
        }

        $domain = $this->findDomainByUrl($previousUrl, $context);
        if (!$domain instanceof SalesChannelDomainEntity) {
            $io->error('No sales channels found with URL ' . $previousUrl);

            return self::FAILURE;
        }

        $this->salesChannelDomainRepository->update([[
            'id' => $domain->getId(),
            'url' => $newUrl,
        ]], $context);

        return self::SUCCESS;
    }

    private function validateUrls(string $previousUrl, string $newUrl, SymfonyStyle $io): bool
    {
        if ($previousUrl === '') {
            $io->error('Previous URL: This value can not be empty');

            return false;
        }

        $validator = Validation::createValidator();
        $newUrlConstraints = [new Url(requireTld: false), new NotEqualTo($previousUrl)];
        $newUrlViolations = $validator->validate($newUrl, $newUrlConstraints);
        if (\count($newUrlViolations) === 0) {
            return true;
        }

        foreach ($newUrlViolations as $violation) {
            $io->error('New URL: ' . $violation->getMessage());
        }

        return false;
    }

    private function findDomainByUrl(string $url, Context $context): ?SalesChannelDomainEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('url', $url));
        $criteria->setLimit(1);

        return $this->salesChannelDomainRepository->search($criteria, $context)->first();
    }
}
