<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Command;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'cache:clear:delayed', description: 'Invalidates the delayed cache keys/tags')]
#[Package('core')]
class CacheInvalidateDelayedCommand extends Command
{
    public function __construct(private readonly CacheInvalidator $invalidator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tags = $this->invalidator->invalidateExpired();

        $style = new SymfonyStyle($input, $output);

        if (empty($tags)) {
            $style->success('No delayed cache tags found');

            return 0;
        }

        if (!$style->isVerbose()) {
            $style->success(sprintf('Invalidated %d delayed cache tags', \count($tags)));

            return 0;
        }

        $table = new Table($output);
        $table->setHeaders(['Tag']);
        $table->setRows(array_map(fn ($tag) => [$tag], $tags));
        $table->render();

        return 0;
    }
}
