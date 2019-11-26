<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Console;

use Symfony\Component\Console\Style\SymfonyStyle;

class ShopwareStyle extends SymfonyStyle
{
    public function createProgressBar($max = 0)
    {
        $progressBar = parent::createProgressBar($max);

        $character = $_SERVER['PROGRESS_BAR_CHARACTER'] ?? '';
        if ($character) {
            $progressBar->setProgressCharacter($character);
        }

        $progressBar->setBarCharacter('<fg=magenta>=</>');

        return $progressBar;
    }
}
