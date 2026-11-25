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
    #[TestDox('without a branch the live layout is the target, guarded by the version token')]
    public function testWithoutBranchTargetsTheLiveLayout(): void
    {
        $target = MutationTarget::fromRequest(null, '2026-06-22T10:00:00.000+00:00', null);

        static::assertFalse($target->isBranch());
        static::assertSame('2026-06-22T10:00:00.000+00:00', $target->expectedVersion);
    }

    #[TestDox('a live target accepts a null version token, which matches a never-updated layout')]
    public function testLiveTargetAcceptsANullVersion(): void
    {
        $target = MutationTarget::fromRequest(null, null, null);

        static::assertFalse($target->isBranch());
        static::assertNull($target->expectedVersion);
    }

    #[TestDox('a branch id makes the branch the target, guarded by the head token')]
    public function testBranchIdTargetsTheBranch(): void
    {
        $target = MutationTarget::fromRequest('branch', null, 'head');

        static::assertTrue($target->isBranch());
        static::assertSame('branch', $target->branchId);
        static::assertSame('head', $target->expectedHead);
    }

    #[TestDox('a live target refuses the head token')]
    public function testLiveTargetRejectsTheHeadToken(): void
    {
        $this->expectExceptionObject(ContentSystemException::unknownRequestField(['expectedHead']));

        MutationTarget::fromRequest(null, null, 'head');
    }

    #[TestDox('a branch target refuses the version token')]
    public function testBranchTargetRejectsTheVersionToken(): void
    {
        $this->expectExceptionObject(ContentSystemException::unknownRequestField(['expectedVersion']));

        MutationTarget::fromRequest('branch', '2026-06-22T10:00:00.000+00:00', 'head');
    }

    #[TestDox('a branch target requires the head token')]
    public function testBranchTargetRequiresTheHeadToken(): void
    {
        $this->expectExceptionObject(ContentSystemException::missingRequestField(['expectedHead']));

        MutationTarget::fromRequest('branch', null, null);
    }
}
