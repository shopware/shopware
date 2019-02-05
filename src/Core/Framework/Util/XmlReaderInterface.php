<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

interface XmlReaderInterface
{
    public function read(string $xmlFile): array;
}
