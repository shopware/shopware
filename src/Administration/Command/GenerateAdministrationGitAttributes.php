<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'administration:generate-git-attributes',
    description: 'Generates the .gitattributes file for the administration.',
)]
#[Package('admin')]
class GenerateAdministrationGitAttributes extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly string $projectDir
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Generating .gitattributes file for the administration...');

        $attributeFile = $this->projectDir . '/src/Administration/.gitattributes';
        $staticContent = <<<EOT
# General ignore rules
/Test export-ignore
/Resources/ide-twig.json export-ignore
/Resources/app/administration/build export-ignore
/Resources/app/administration/test export-ignore
/Resources/app/administration/src/meta export-ignore
/Resources/app/administration/src/scripts/create-spec-file export-ignore

# Ignore all spec files

EOT;

        \file_put_contents($attributeFile, $staticContent);

        $finder = new Finder();
        $files = $finder->in($this->projectDir . '/src/Administration/Resources/app/administration')
            ->notPath('node_modules')
            ->name('/.*\.spec\.(js|ts)/')
            ->files()
            ->getIterator();

        $files = \iterator_to_array($files);
        \sort($files);

        $handler = \fopen($attributeFile, 'ab');
        if (!$handler) {
            throw new \RuntimeException('Could not open file for writing: ' . $attributeFile);
        }

        foreach ($files as $file) {
            fwrite($handler, '/Resources/app/administration/' . $file->getRelativePathname() . ' export-ignore' . \PHP_EOL);
        }

        fclose($handler);

        $output->writeln('Done!');

        return 0;
    }
}
