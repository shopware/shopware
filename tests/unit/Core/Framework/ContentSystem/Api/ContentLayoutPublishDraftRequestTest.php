<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutPublishDraftRequest;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutPublishDraftRequest::class)]
class ContentLayoutPublishDraftRequestTest extends TestCase
{
    public function testCarriesTheDraftAndTheHeadTheCallerLastSaw(): void
    {
        $request = new ContentLayoutPublishDraftRequest('draft-1', 'revision-1');

        static::assertSame('draft-1', $request->draftId);
        static::assertSame('revision-1', $request->expectedHeadRevisionId);
    }
}
