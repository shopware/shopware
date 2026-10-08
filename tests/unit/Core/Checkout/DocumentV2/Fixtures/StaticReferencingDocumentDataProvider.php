<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\Provider\ReferencesDocument;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class StaticReferencingDocumentDataProvider extends StaticDocumentDataProvider implements ReferencesDocument
{
}
