<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VerifyManifestCommand extends Command
{
    protected static $defaultName = 'app:verify';

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        /** @var array<string> $manifestPaths */
        $manifestPaths = $input->getArgument('manifests');

        $invalidCount = 0;

        foreach ($manifestPaths as $manifestPath) {
            try {
                Manifest::createFromXmlFile($manifestPath);
            } catch (XmlParsingException $e) {
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
}
