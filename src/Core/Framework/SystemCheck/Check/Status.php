<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck\Check;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum Status implements \JsonSerializable
{
    case OK;
    case UNKNOWN;
    case SKIPPED;
    case WARNING;
    case ERROR;
    case FAILURE;

    public function jsonSerialize(): string
    {
        return $this->name;
    }
}
