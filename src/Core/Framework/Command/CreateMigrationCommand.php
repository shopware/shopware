<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateMigrationCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->addArgument('directory', InputArgument::REQUIRED)
            ->addArgument('namespace', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $timestamp = (new \DateTime())->getTimestamp();
        $output->writeln('Create Migration with timestamp: "' . $timestamp . '"');
        $directory = (string) $input->getArgument('directory');
        $namespace = (string) $input->getArgument('namespace');

        $path = rtrim($directory, '/') . '/Migration' . $timestamp . '.php';
        $file = fopen($path, 'w');

        $template = file_get_contents(realpath(__DIR__ . '/../Migration/Template/MigrationTemplate.txt'));

        fwrite($file, str_replace(['%%namespace%%', '%%timestamp%%'], [$namespace, $timestamp], $template));
        fclose($file);

        $output->writeln('Migration Created: "' . $path . '"');
    }
}
