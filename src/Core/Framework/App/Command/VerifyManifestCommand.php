<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\ManifestValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Webhook\Exception\HookableValidationException;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

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
        $context = Context::createDefaultContext();

        /** @var array<string> $manifestPaths */
        $manifestPaths = $input->getArgument('manifests');

        if (empty($manifestPaths)) {
            $manifestPaths = $this->findManifestsPaths();
        }

        $invalidCount = 0;

        foreach ($manifestPaths as $manifestPath) {
            try {
                $manifest = Manifest::createFromXmlFile($manifestPath);
                $this->manifestValidator->validate($manifest, $context);
            } catch (XmlParsingException | HookableValidationException $e) {
                $io->error($e->getMessage());
                ++$invalidCount;
            }
        }
        if ($invalidCount > 0) {
            return 1;
        }
        $io->success('all files valid');

        return 0;
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
