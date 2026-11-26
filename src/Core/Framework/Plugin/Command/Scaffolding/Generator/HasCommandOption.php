<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Input\InputOption;

/**
 * @internal
 */
#[Package('framework')]
trait HasCommandOption
{
    public function hasCommandOption(): bool
    {
        return true;
    }

    public function getCommandOptionName(): string
    {
        return self::OPTION_NAME;
    }

    public function getCommandOptionDescription(): string
    {
        return self::OPTION_DESCRIPTION;
    }

    public function getCommandOption(): InputOption
    {
        return new InputOption(self::OPTION_NAME, null, InputOption::VALUE_NONE, self::OPTION_DESCRIPTION);
    }

    public function getCommandOptionTitle(): string
    {
        return self::OPTION_TITLE;
    }

    public function getCommandOptionDescriptionLong(): string
    {
        return self::OPTION_DESCRIPTION_LONG;
    }
}
