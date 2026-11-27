<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutDiscardDraftRequest;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutDiscardDraftRequest::class)]
class ContentLayoutDiscardDraftRequestTest extends TestCase
{
    public function testCarriesTheDraftToDiscard(): void
    {
        static::assertSame('draft-1', (new ContentLayoutDiscardDraftRequest('draft-1'))->draftId);
    }
}
