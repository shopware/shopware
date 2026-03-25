<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Command;

use Shopware\Core\Framework\Log\Package;
use SVG\Nodes\Structures\SVGDefs;
use SVG\Nodes\Structures\SVGUse;
use SVG\Nodes\SVGNode;
use SVG\Nodes\SVGNodeContainer;
use SVG\Reading\SVGReader;
use SVG\SVG;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

use function Symfony\Component\String\s;

#[AsCommand(
    name: 'theme:prepare-icons',
    description: 'Prepare the theme icons',
)]
#[Package('framework')]
class ThemePrepareIconsCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path')
            ->addArgument('package', InputArgument::REQUIRED, 'Package name')
            ->addOption('fillcolor', 'f', InputOption::VALUE_REQUIRED, 'color for fill attribute in use tag')
            ->addOption('fillrule', 'r', InputOption::VALUE_REQUIRED, 'fill-rule attribute for use tag')
            ->addOption('cleanup', 'c', InputOption::VALUE_REQUIRED, 'cleanup all unnecessary attributes cleanup=true');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = rtrim((string) $input->getArgument('path'), '/') . '/';
        $package = $input->getArgument('package');

        $fillcolor = $input->getOption('fillcolor');
        $fillrule = $input->getOption('fillrule');
        $verbose = $input->getOption('verbose');

        $cleanup = $input->getOption('cleanup');
        if (\is_string($cleanup) && $cleanup !== 'true' && $cleanup !== 'false') {
            $io->writeln(\sprintf('Option cleanup can either be "true" or "false" but option is "%s" and will be handled as "false"', $cleanup));
        }

        $cleanup = $cleanup === 'true';

        if ($cleanup) {
            $io->writeln('Cleanup is set. Processed Icons will be automatically cleaned. Please check the outcome.');
        }

        $io->writeln('Start Icon preparation');
        $svgReader = new SVGReader();

        $fs = new Filesystem();
        $fs->mkdir($path . 'processed/');
        $io->writeln('Created sub directory "processed" in working directory ' . str_replace(__DIR__, '', $path) . '.');
        $io->writeln('The processed icons will be written in the "processed" sub directory.');

        $files = (new Finder())->files()->in($path)->name('*.svg')->exclude('processed');
        $processedCount = 0;
        if ($files->count() === 0) {
            $io->warning('No svg files found in ' . $path);

            return self::SUCCESS;
        }

        foreach ($files as $file) {
            $svg = $file->getContents();

            if ($svg === '') {
                $io->warning('Could not read ' . $file . '.You have to handle this file by hand.');

                continue;
            }

            try {
                $svg = $svgReader->parseString($svg);
                if (!$svg instanceof SVG) {
                    $io->warning('Could not read ' . $file . '.You have to handle this file by hand.');

                    continue;
                }
            } catch (\Throwable $e) {
                $io->warning($e->getMessage() . ' ' . $file . \PHP_EOL . 'You have to handle this file by hand.');

                continue;
            }

            $defs = $svg->getDocument()->getChild(0);
            if (!$defs instanceof SVGDefs) {
                $defs = new SVGDefs();
                foreach ($this->getChildren($svg->getDocument()) as $documentChild) {
                    $svg->getDocument()->removeChild($documentChild);
                    $defs->addChild($documentChild);
                }
                $svg->getDocument()->addChild($defs);
            }

            $child = $defs->getChild(0);

            if ($child->getAttribute('id') === null || $cleanup) {
                $id = 'icons-' . $package . '-' . s($file->getBasename('.svg'))->kebab()->toString();
                $child->setAttribute('id', $id);
            } else {
                $id = $child->getAttribute('id');
            }

            $use = null;
            foreach ($this->getChildren($svg->getDocument()) as $documentChild) {
                if ($documentChild instanceof SVGUse) {
                    $use = $documentChild;
                }
            }

            if ($use === null) {
                $use = new SVGUse();
            }

            $use->setAttribute('xlink:href', '#' . $id);
            if ($fillcolor) {
                $use->setAttribute('fill', $fillcolor);
            } elseif ($cleanup) {
                $use->removeAttribute('fill');
            }
            if ($fillrule) {
                $use->setAttribute('fill-rule', $fillrule);
            } elseif ($cleanup) {
                $use->removeAttribute('fill-rule');
            }

            $svg->getDocument()->addChild($use);

            if ($cleanup) {
                $this->removeStyles($svg->getDocument());
            }

            $fs->dumpFile($path . 'processed/' . $file->getBasename(), $svg->toXMLString(false));

            if ($verbose) {
                $io->writeln('Icon ' . $file . ' processed');
            }
            ++$processedCount;
        }

        $io->success('Processed ' . $processedCount . ' icons');

        return self::SUCCESS;
    }

    protected function removeStyles(SVGNode $child): void
    {
        foreach (array_keys($child->getSerializableStyles()) as $key) {
            $child->removeStyle($key);
        }

        if ($child instanceof SVGNodeContainer && $child->countChildren() > 0) {
            foreach ($this->getChildren($child) as $grandChild) {
                $this->removeStyles($grandChild);
            }
        }
    }

    /**
     * @return list<SVGNode>
     */
    private function getChildren(SVGNodeContainer $fragment): array
    {
        $children = [];
        for ($x = 0; $x < $fragment->countChildren(); ++$x) {
            $children[] = $fragment->getChild($x);
        }

        return $children;
    }
}
