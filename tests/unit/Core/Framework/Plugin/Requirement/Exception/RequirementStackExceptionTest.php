<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Requirement\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Requirement\Exception\MissingRequirementException;
use Shopware\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RequirementStackException::class)]
class RequirementStackExceptionTest extends TestCase
{
    #[TestDox('the message aggregates method, failure count and every inner message')]
    public function testMessageAggregation(): void
    {
        $exception = new RequirementStackException(
            'install',
            new MissingRequirementException('shopware/core', '~6.7'),
            new MissingRequirementException('swag/paypal', '*'),
        );

        static::assertSame('FRAMEWORK__PLUGIN_REQUIREMENTS_FAILED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_FAILED_DEPENDENCY, $exception->getStatusCode());
        static::assertCount(2, $exception->getRequirements());
        static::assertStringContainsString('Could not install plugin, got 2 failure(s).', $exception->getMessage());
        static::assertStringContainsString('Required plugin/package "shopware/core ~6.7" is missing', $exception->getMessage());
        static::assertStringContainsString('Required plugin/package "swag/paypal *" is missing', $exception->getMessage());
    }

    #[TestDox('getErrors yields the inner requirement errors')]
    public function testGetErrors(): void
    {
        $exception = new RequirementStackException(
            'update',
            new MissingRequirementException('shopware/core', '~6.7'),
        );

        $errors = iterator_to_array($exception->getErrors(), false);

        static::assertCount(1, $errors);
        static::assertSame('FRAMEWORK__PLUGIN_REQUIREMENT_MISSING', $errors[0]['code']);
        static::assertStringContainsString('shopware/core ~6.7', (string) $errors[0]['detail']);
    }
}
