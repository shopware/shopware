<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Command;

use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'open-api:generate-dtos',
    description: 'Generates PHP DTO classes from the OpenAPI schema definitions',
)]
final class OpenApiDtoGenerationCommand extends Command
{
    public function __construct(
        private readonly OpenApiDtoGenerator $generator,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Checks whether generated DTO files are up to date without writing them.')]
        bool $check = false,
    ): int {
        if ($check) {
            return $this->doCheck($io);
        }

        $result = $this->generator->generate();

        $io->success(\sprintf('Generated %d PHP DTO file(s).', \count($result->writtenFiles)));

        foreach ($result->writtenFiles as $file) {
            $io->writeln(' - ' . $file);
        }

        return Command::SUCCESS;
    }

    private function doCheck(SymfonyStyle $io): int
    {
        $result = $this->generator->check();

        if ($result->outdatedFiles === []) {
            $io->success('Generated DTO files are up to date.');

            return Command::SUCCESS;
        }

        $io->error('Generated DTO files are not up to date.');
        $io->listing($result->outdatedFiles);
        $io->comment('Run bin/console open-api:generate-dtos and commit the result.');

        return Command::FAILURE;
    }
}
