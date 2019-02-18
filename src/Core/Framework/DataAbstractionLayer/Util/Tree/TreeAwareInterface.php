<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Util\Tree;

interface TreeAwareInterface
{
    public function getId(): string;

    public function getPath(): ?string;

    public function getParentId(): ?string;
}
