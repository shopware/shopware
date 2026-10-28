<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaFileExtensionWriteValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Assert\WriteViolations;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('discovery')]
class MediaFileExtensionWriteValidatorTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testTheExtensionIsComparedIgnoringCase(): void
    {
        WriteViolations::assertAccepted(fn () => $this->createMedia(['fileExtension' => 'PNG']));
    }

    public function testANewMediaWithoutVisibilityCannotHaveAPrivateOnlyExtension(): void
    {
        WriteViolations::assertRejected(
            [MediaFileExtensionWriteValidator::MEDIA_ILLEGAL_FILE_EXTENSION],
            fn () => $this->createMedia(['fileExtension' => 'zip']),
        );
    }

    public function testAStoredPrivateMediaMayGetAPrivateOnlyExtension(): void
    {
        $this->createMedia(['private' => true]);

        WriteViolations::assertAccepted(fn () => $this->updateMedia(['fileExtension' => 'zip']));
    }

    public function testAStoredPublicMediaCannotGetAPrivateOnlyExtension(): void
    {
        $this->createMedia(['private' => false]);

        WriteViolations::assertRejected(
            [MediaFileExtensionWriteValidator::MEDIA_ILLEGAL_FILE_EXTENSION],
            fn () => $this->updateMedia(['fileExtension' => 'zip']),
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createMedia(array $values): void
    {
        static::getContainer()->get('media.repository')->create([['id' => $this->ids->get('media'), ...$values]], Context::createDefaultContext());
    }

    /**
     * @param array<string, mixed> $values
     */
    private function updateMedia(array $values): void
    {
        static::getContainer()->get('media.repository')->update([['id' => $this->ids->get('media'), ...$values]], Context::createDefaultContext());
    }
}
