<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\Provider\RendersReferencedSnapshot;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class StaticReferencedSnapshotDocumentDataProvider extends StaticReferencingDocumentDataProvider implements RendersReferencedSnapshot
{
}
