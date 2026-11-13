<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\AppValidator;
use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\App\Validation\Error\Severity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
#[AsCommand(
    name: 'app:validate',
    description: 'Validates an app',
)]
class ValidateAppCommand extends Command
{
    public function __construct(
        private readonly string $appDir,
        private readonly AppValidator $appValidator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = $this->appDir; // validate all apps as default

        $name = $input->getArgument('name');

        if ($name !== '' && \is_string($name)) {
            $dir = $this->getAppFolderByName($name, $io);

            if ($dir === null) {
                return self::FAILURE;
            }
        }

        return $this->validate($dir, $io);
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'The name of the app, has also to be the name of the folder under which the app can be found under custom/apps.');
    }

    private function validate(string $appDir, SymfonyStyle $io): int
    {
        try {
            $manifests = $this->getManifestsFromDir($appDir);
        } catch (AppXmlParsingException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $context = Context::createCLIContext();
        $invalidApps = 0;
        $failures = 0;
        $notices = 0;

        foreach ($manifests as $manifest) {
            $errors = $this->appValidator->validate($manifest, $context)->errors ?? [];
            $this->printErrors($io, $manifest, $errors);

            $appFailures = \count(array_filter($errors, static fn (Error $error): bool => $error->getSeverity() === Severity::Fail));
            $failures += $appFailures;
            $notices += \count($errors) - $appFailures;
            $invalidApps += $appFailures > 0 ? 1 : 0;
        }

        $this->printSummary($io, \count($manifests), $invalidApps, $failures, $notices);

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<Manifest>
     */
    private function getManifestsFromDir(string $dir): array
    {
        if (!\is_dir($dir)) {
            throw AppException::manifestNotFound($dir);
        }

        $finder = new Finder();
        $finder->in($dir)
            ->depth('<= 1')
            ->name('manifest.xml');

        $manifests = [];
        foreach ($finder->files() as $xml) {
            $manifests[] = Manifest::createFromXmlFile($xml->getPathname());
        }

        if ($manifests === []) {
            throw AppException::manifestNotFound($dir);
        }

        return $manifests;
    }

    private function getAppFolderByName(string $name, SymfonyStyle $io): ?string
    {
        $finder = new Finder();
        $finder->in($this->appDir)
            ->depth('<= 1')
            ->name($name);

        $folders = [];
        foreach ($finder->directories() as $dir) {
            $folders[] = $dir->getPathname();
        }

        if ($folders === []) {
            $io->error(
                \sprintf(
                    'No app with name "%s" found. Please make sure that a folder with that exact name exist in the custom/apps folder.',
                    $name
                )
            );

            return null;
        }

        return $folders[0];
    }

    /**
     * @param list<Error> $errors
     */
    private function printErrors(SymfonyStyle $io, Manifest $manifest, array $errors): void
    {
        if ($errors === []) {
            return;
        }

        $io->newLine();
        $io->writeln(\sprintf('<options=bold>%s</>', $manifest->getMetadata()->getName()));

        foreach ($errors as $error) {
            $label = $error->getSeverity() === Severity::Fail ? '<fg=red>error </>' : '<fg=yellow>notice</>';
            $io->writeln(\sprintf('  %s  %s', $label, $error->getMessage()));
        }
    }

    private function printSummary(SymfonyStyle $io, int $apps, int $invalidApps, int $failures, int $notices): void
    {
        if ($failures > 0) {
            $io->newLine();
            $io->writeln(\sprintf(
                '<fg=red;options=bold>✖ %s, %s (%d of %s invalid)</>',
                $this->pluralize($failures, 'error'),
                $this->pluralize($notices, 'notice'),
                $invalidApps,
                $this->pluralize($apps, 'app')
            ));

            return;
        }

        if ($notices > 0) {
            $io->newLine();
            $io->writeln(\sprintf('<fg=green;options=bold>✔ 0 errors, %s</>', $this->pluralize($notices, 'notice')));

            return;
        }

        $io->writeln(\sprintf('<fg=green;options=bold>✔ %s valid</>', $this->pluralize($apps, 'app')));
    }

    private function pluralize(int $count, string $noun): string
    {
        return \sprintf('%d %s%s', $count, $noun, $count === 1 ? '' : 's');
    }
}
