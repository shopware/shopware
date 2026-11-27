<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\MutationTarget;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MutationTarget::class)]
class MutationTargetTest extends TestCase
{
    #[TestDox('without a draft the live layout is the target, guarded by the version token')]
    public function testWithoutDraftTargetsTheLiveLayout(): void
    {
        $target = MutationTarget::fromRequest(null, '2026-06-22T10:00:00.000+00:00', null);

        static::assertFalse($target->isDraft());
        static::assertSame('2026-06-22T10:00:00.000+00:00', $target->expectedVersion);
    }

    #[TestDox('a live target accepts a null version token, which matches a never-updated layout')]
    public function testLiveTargetAcceptsANullVersion(): void
    {
        $target = MutationTarget::fromRequest(null, null, null);

        static::assertFalse($target->isDraft());
        static::assertNull($target->expectedVersion);
    }

    #[TestDox('a draft id makes the draft the target, guarded by the head token')]
    public function testDraftIdTargetsTheDraft(): void
    {
        $target = MutationTarget::fromRequest('draft', null, 'head');

        static::assertTrue($target->isDraft());
        static::assertSame('draft', $target->draftId);
        static::assertSame('head', $target->expectedHeadRevisionId);
    }

    #[TestDox('a live target refuses the head token')]
    public function testLiveTargetRejectsTheHeadToken(): void
    {
        $this->expectExceptionObject(ContentSystemException::mutationTokenTargetMismatch('expectedHeadRevisionId', false));

        MutationTarget::fromRequest(null, null, 'head');
    }

    #[TestDox('a draft target refuses the version token')]
    public function testDraftTargetRejectsTheVersionToken(): void
    {
        $this->expectExceptionObject(ContentSystemException::mutationTokenTargetMismatch('expectedVersion', true));

        MutationTarget::fromRequest('draft', '2026-06-22T10:00:00.000+00:00', 'head');
    }

    #[TestDox('a draft target requires the head token')]
    public function testDraftTargetRequiresTheHeadToken(): void
    {
        $this->expectExceptionObject(ContentSystemException::missingRequestField(['expectedHeadRevisionId']));

        MutationTarget::fromRequest('draft', null, null);
    }
}
