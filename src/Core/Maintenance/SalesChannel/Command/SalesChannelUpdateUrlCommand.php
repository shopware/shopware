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
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[AsCommand(
    name: 'sales-channel:update:url',
    description: 'Updates a sales channel with a new URL',
)]
#[Package('discovery')]
class SalesChannelUpdateUrlCommand extends Command
{
    /**
     * @internal
     *
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

        $domains = $this->findDomainsByUrl($previousUrl, $context);
        if ($domains->count() === 0) {
            $io->error('No sales channels found with URL ' . $previousUrl);

            return self::FAILURE;
        }

        $payload = $this->buildUpdatePayload($domains, $newUrl);
        if ($payload === []) {
            $io->warning('All matching domains already have the new URL. No updates needed.');

            return self::SUCCESS;
        }

        $this->salesChannelDomainRepository->update($payload, $context);

        return self::SUCCESS;
    }

    private function validateUrls(string $previousUrl, string $newUrl, SymfonyStyle $io): bool
    {
        $validator = Validation::createValidator();

        $previousUrlConstraints = [new NotBlank()];
        $previousUrlViolations = $validator->validate($previousUrl, $previousUrlConstraints);
        if (\count($previousUrlViolations) > 0) {
            foreach ($previousUrlViolations as $violation) {
                $io->error('Previous URL: ' . $violation->getMessage());
            }

            return false;
        }

        $newUrlConstraints = [new NotBlank(), new Url(requireTld: false), new NotEqualTo($previousUrl)];
        $newUrlViolations = $validator->validate($newUrl, $newUrlConstraints);
        if (\count($newUrlViolations) > 0) {
            foreach ($newUrlViolations as $violation) {
                $io->error('New URL: ' . $violation->getMessage());
            }

            return false;
        }

        return true;
    }

    private function findDomainsByUrl(string $url, Context $context): SalesChannelDomainCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('url', $url));

        $entities = $this->salesChannelDomainRepository->search($criteria, $context)->getEntities();
        \assert($entities instanceof SalesChannelDomainCollection);

        return $entities;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildUpdatePayload(SalesChannelDomainCollection $domains, string $newUrl): array
    {
        $payload = [];

        /** @var SalesChannelDomainEntity $domain */
        foreach ($domains as $domain) {
            if ($newUrl === $domain->getUrl()) {
                continue;
            }

            $payload[] = [
                'id' => $domain->getId(),
                'url' => $newUrl,
            ];
        }

        return $payload;
    }
}
