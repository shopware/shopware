<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\Exception\AppValidationException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\ManifestValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * @deprecated tag:v6.4.0.0 - Use `Shopware\Core\Framework\App\Command\ValidateAppCommand` instead
 */
class VerifyManifestCommand extends Command
{
    protected static $defaultName = 'app:verify';

    /**
     * @var ManifestValidator
     */
    private $manifestValidator;

    /**
     * @var string
     */
    private $appDir;

    public function __construct(ManifestValidator $manifestValidator, string $appDir)
    {
        parent::__construct();
        $this->manifestValidator = $manifestValidator;
        $this->appDir = $appDir;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        /** @var array<string> $manifestPaths */
        $manifestPaths = $input->getArgument('manifests');

        if (empty($manifestPaths)) {
            $manifestPaths = $this->findManifestsPaths();
        }

        $invalids = $this->verify($manifestPaths);

        if (\count($invalids) > 0) {
            foreach ($invalids as $invalid) {
                $io->error($invalid);
            }

            return 1;
        }

        $io->success('all files valid');

        return 0;
    }

    public function verify(array $manifestPaths): array
    {
        $context = Context::createDefaultContext();

        $invalids = [];
        foreach ($manifestPaths as $manifestPath) {
            try {
                $manifest = Manifest::createFromXmlFile($manifestPath);
                $this->manifestValidator->validate($manifest, $context);
            } catch (XmlParsingException | AppValidationException $e) {
                $invalids[] = $e->getMessage();
            }
        }

        return $invalids;
    }

    protected function configure(): void
    {
        $this->setDescription('checks manifests for errors')
            ->addArgument('manifests', InputArgument::IS_ARRAY, 'The paths of the manifest file');
    }

    private function findManifestsPaths(): array
    {
        if (!file_exists($this->appDir)) {
            return [];
        }

        $finder = new Finder();
        $finder->in($this->appDir)
            ->name('manifest.xml');

        $manifests = [];
        foreach ($finder->files() as $xml) {
            $manifests[] = $xml->getPathname();
        }

        return $manifests;
    }
}
