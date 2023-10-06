<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\Exception\AppValidationException;
use Shopware\Core\Framework\App\Manifest\Exception\ManifestNotFoundException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\ManifestValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * @internal only for use by the app-system
 */
#[AsCommand(
    name: 'app:validate',
    description: 'Validates an app',
)]
#[Package('core')]
class ValidateAppCommand extends Command
{
    public function __construct(
        private readonly string $appDir,
        private readonly ManifestValidator $manifestValidator
    ) {
        parent::__construct();
    }

    public function validate(string $appDir): array
    {
        $context = Context::createDefaultContext();
        $invalids = [];

        try {
            $manifests = $this->getManifestsFromDir($appDir);

            foreach ($manifests as $manifest) {
                try {
                    $this->manifestValidator->validate($manifest, $context);
                } catch (AppValidationException $e) {
                    $invalids[] = $e->getMessage();
                }
            }
        } catch (XmlParsingException $e) {
            $invalids[] = $e->getMessage();
        }

        return $invalids;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $dir = $this->appDir; // validate all apps as default
        $successMessage = 'all apps valid';

        $name = $input->getArgument('name');

        if ($name !== '' && \is_string($name)) {
            $successMessage = 'app is valid';
            $dir = $this->getAppFolderByName($name, $io);

            if ($dir === null) {
                return self::FAILURE;
            }
        }

        $invalids = $this->validate($dir);

        if (\count($invalids) > 0) {
            foreach ($invalids as $invalid) {
                $io->error($invalid);
            }

            return self::FAILURE;
        }

        $io->success($successMessage);

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'The name of the app, has also to be the name of the folder under which the app can be found under custom/apps.');
    }

    /**
     * @return Manifest[]
     */
    private function getManifestsFromDir(string $dir): array
    {
        if (!file_exists($dir)) {
            throw new ManifestNotFoundException($dir);
        }

        $finder = new Finder();
        $finder->in($dir)
            ->depth('<= 1')
            ->name('manifest.xml');

        $manifests = [];
        foreach ($finder->files() as $xml) {
            $manifests[] = Manifest::createFromXmlFile($xml->getPathname());
        }

        if (\count($manifests) === 0) {
            throw new ManifestNotFoundException($dir);
        }

        return $manifests;
    }

    private function getAppFolderByName(string $name, ShopwareStyle $io): ?string
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
                sprintf(
                    'No app with name "%s" found. Please make sure that a folder with that exact name exist in the custom/apps folder.',
                    $name
                )
            );

            return null;
        }

        return $folders[0];
    }
}
