<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Contract;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface IdAware
{
    public function getId(): string;
}
