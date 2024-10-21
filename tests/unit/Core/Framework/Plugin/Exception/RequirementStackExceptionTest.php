<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Requirement\Exception\MissingRequirementException;
use Shopware\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;
use Shopware\Core\Framework\Plugin\Requirement\Exception\VersionMismatchException;

/**
 * @internal
 */
#[CoversClass(RequirementStackException::class)]
class RequirementStackExceptionTest extends TestCase
{
    public function testDoesNotConvertInnerExceptions(): void
    {
        $requirement = 'testRequirement';
        $version = 'v1.0';
        $actualVersion = 'v2.0';
        $action = 'install';

        $missingRequirementException = new MissingRequirementException($requirement, $version);
        $versionMismatchException = new VersionMismatchException($requirement, $version, $actualVersion);

        $requirementStackException = new RequirementStackException(
            $action,
            $missingRequirementException,
            $versionMismatchException
        );

        $converted = [];
        foreach ($requirementStackException->getErrors() as $exception) {
            $converted[] = $exception;
        }

        $convertedVersionMismatch = iterator_to_array($versionMismatchException->getErrors())[0];

        static::assertCount(2, $converted);

        static::assertEquals('424', $converted[0]['status']);
        static::assertEquals('FRAMEWORK__PLUGIN_REQUIREMENT_MISSING', $converted[0]['code']);

        static::assertEquals($convertedVersionMismatch, $converted[1]);
    }
}
