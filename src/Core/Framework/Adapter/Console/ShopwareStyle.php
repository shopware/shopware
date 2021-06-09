<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Console;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Symfony\Component\Console\Style\SymfonyStyle;

class ShopwareStyle extends SymfonyStyle
{
    public function createProgressBar(int $max = 0)
    {
        $progressBar = parent::createProgressBar($max);

        $character = (string) EnvironmentHelper::getVariable('PROGRESS_BAR_CHARACTER', '');
        if ($character) {
            $progressBar->setProgressCharacter($character);
        }

        $progressBar->setBarCharacter('<fg=magenta>=</>');

        return $progressBar;
    }
}
