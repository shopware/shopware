<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\AbstractFileContentValidator;
use Shopware\Core\Content\Media\File\FileContentValidationStrategy;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FileContentValidationStrategy::class)]
class FileContentValidationStrategyTest extends TestCase
{
    public function testValidateExecutesOnlySupportingValidators(): void
    {
        $mediaFile = $this->createMediaFile('svg');
        $supportingValidator = $this->createMock(AbstractFileContentValidator::class);
        $nonSupportingValidator = $this->createMock(AbstractFileContentValidator::class);

        $supportingValidator->expects($this->once())
            ->method('supports')
            ->with($mediaFile)
            ->willReturn(true);
        $supportingValidator->expects($this->once())
            ->method('validate')
            ->with($mediaFile);

        $nonSupportingValidator->expects($this->once())
            ->method('supports')
            ->with($mediaFile)
            ->willReturn(false);
        $nonSupportingValidator->expects($this->never())
            ->method('validate');

        $validator = new FileContentValidationStrategy([$supportingValidator, $nonSupportingValidator]);

        $validator->validate($mediaFile);
    }

    public function testValidateRunsAllMatchingValidators(): void
    {
        $mediaFile = $this->createMediaFile('svg');
        $firstValidator = $this->createSupportingValidator($mediaFile);
        $secondValidator = $this->createSupportingValidator($mediaFile);

        $validator = new FileContentValidationStrategy([$firstValidator, $secondValidator]);

        $validator->validate($mediaFile);
    }

    private function createMediaFile(string $extension): MediaFile
    {
        return new MediaFile('/tmp/example.' . $extension, 'image/' . $extension, $extension, 1);
    }

    /**
     * @return MockObject&AbstractFileContentValidator
     */
    private function createSupportingValidator(MediaFile $mediaFile): AbstractFileContentValidator
    {
        $validator = $this->createMock(AbstractFileContentValidator::class);
        $validator->expects($this->once())
            ->method('supports')
            ->with($mediaFile)
            ->willReturn(true);
        $validator->expects($this->once())
            ->method('validate')
            ->with($mediaFile);

        return $validator;
    }
}
