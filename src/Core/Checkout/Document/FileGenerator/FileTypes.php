<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\FileGenerator;

use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
class FileTypes
{
    final public const PDF = 'pdf';
}
