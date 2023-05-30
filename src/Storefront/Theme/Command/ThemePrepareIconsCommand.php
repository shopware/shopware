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

#[AsCommand(
    name: 'theme:prepare-icons',
    description: 'Prepare the theme icons',
)]
#[Package('storefront')]
class ThemePrepareIconsCommand extends Command
{
    private SymfonyStyle $io;

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path');
        $this->addArgument('package', InputArgument::REQUIRED, 'Package name');
        $this->addOption('fillcolor', 'f', InputOption::VALUE_REQUIRED, 'color for fill attribute in use tag');
        $this->addOption('fillrule', 'r', InputOption::VALUE_REQUIRED, 'fill-rule attribute for use tag');
        $this->addOption('cleanup', 'c', InputOption::VALUE_REQUIRED, 'cleanup all unnecessary attributes cleanup=true');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $path = rtrim((string) $input->getArgument('path'), '/') . '/';
        $package = $input->getArgument('package');

        $fillcolor = $input->getOption('fillcolor');
        $fillrule = $input->getOption('fillrule');
        $verbose = $input->getOption('verbose');

        if (
            !empty($input->getOption('cleanup'))
            && $input->getOption('cleanup') !== 'true'
            && $input->getOption('cleanup') !== 'false'
        ) {
            $this->io->writeln(
                'Option cleanup can either be "true" or "false" but option is "'
                . $input->getOption('cleanup') . '" and will be handled as "false"'
            );
        }

        $cleanup = $input->getOption('cleanup') === 'true';

        if ($cleanup) {
            $this->io->writeln(
                'Cleanup is set. Processed Icons will be automatically cleaned. Please check the outcome.'
            );
        }

        $this->io = new SymfonyStyle($input, $output);

        $this->io->writeln('Start Icon preparation');
        $svgReader = new SVGReader();
        @mkdir($path . 'processed/');
        $this->io->writeln('Created sub directory "processed" in working directory ' . str_replace(__DIR__, '', $path) . '.');
        $this->io->writeln('The processed icons will be written in the "processed" sub directory.');

        $files = glob($path . '*.svg');
        $processedCount = 0;
        if (!\is_array($files)) {
            $this->io->warning('No svg files found in ' . $path);

            return self::SUCCESS;
        }
        foreach ($files as $file) {
            $svg = file_get_contents($file);

            if (!\is_string($svg)) {
                $this->io->warning('Could not read ' . $file . '.You have to handle this file by hand.');

                continue;
            }

            try {
                $svg = $svgReader->parseString($svg);
                if (!($svg instanceof SVG)) {
                    $this->io->warning('Could not read ' . $file . '.You have to handle this file by hand.');

                    continue;
                }
            } catch (\Exception $e) {
                $this->io->warning($e->getMessage() . ' ' . $file . \PHP_EOL . 'You have to handle this file by hand.');

                continue;
            }

            $defs = $svg->getDocument()->getChild(0);
            if (!($defs instanceof SVGDefs)) {
                $defs = new SVGDefs();
                foreach ($this->getChildren($svg->getDocument()) as $child) {
                    $svg->getDocument()->removeChild($child);
                    $defs->addChild($child);
                }
                $svg->getDocument()->addChild($defs);
            }

            $child = $defs->getChild(0);

            if ($child->getAttribute('id') === null || $cleanup) {
                $id = 'icons-' . $package . '-' . self::toKebabCase(basename($file, '.svg'));
                $child->setAttribute('id', $id);
            } else {
                $id = $child->getAttribute('id');
            }

            $use = null;
            foreach ($this->getChildren($svg->getDocument()) as $child) {
                if ($child instanceof SVGUse) {
                    $use = $child;
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

            file_put_contents($path . 'processed/' . basename($file), $svg->toXMLString(false));

            if ($verbose) {
                $this->io->writeln('Icon ' . $file . ' processed');
            }
            ++$processedCount;
        }

        $this->io->success('Processed ' . $processedCount . ' icons');

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

    private function getChildren(SVGNodeContainer $fragment): array
    {
        $children = [];
        for ($x = 0; $x < $fragment->countChildren(); ++$x) {
            $children[] = $fragment->getChild($x);
        }

        return $children;
    }

    private static function toKebabCase(string $str): string
    {
        return (string) preg_replace('/[^a-z0-9\-]/', '-', strtolower($str));
    }
}
