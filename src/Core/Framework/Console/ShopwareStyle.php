<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Console;

use Symfony\Component\Console\Style\SymfonyStyle;

class ShopwareStyle extends SymfonyStyle
{
    public function createProgressBar($max = 0)
    {
        $progressBar = parent::createProgressBar($max);

        $date = new \DateTime();
        if (((int) $date->format('H')) >= 16) {
            $progressBar->setProgressCharacter("\xF0\x9F\x8D\xBA");
        }

        $progressBar->setBarCharacter('<fg=magenta>=</>');

        return $progressBar;
    }
}
