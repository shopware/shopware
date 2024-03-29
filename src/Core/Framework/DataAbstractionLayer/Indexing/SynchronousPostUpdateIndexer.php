<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class SynchronousPostUpdateIndexer extends PostUpdateIndexer
{
}
