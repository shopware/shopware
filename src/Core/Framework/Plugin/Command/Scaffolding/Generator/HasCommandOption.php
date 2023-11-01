<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
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
}
