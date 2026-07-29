<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\RedisGroupUsage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RedisGroupUsage::class)]
class RedisGroupUsageTest extends TestCase
{
    #[TestDox('Fails when a change adds #[Group(\'redis\')], pointing to the redis testsuite')]
    #[DataProvider('patchProvider')]
    public function testRedisGroupDetection(string $fileName, string $status, string $patch, bool $expectFailure): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile($fileName, $status, '', $patch),
        ])));

        (new RedisGroupUsage())($context);

        static::assertSame($expectFailure, $context->hasFailures());
        if ($expectFailure) {
            static::assertStringContainsString('redis` testsuite', $context->getFailures()[0]);
            static::assertStringContainsString($fileName, $context->getFailures()[0]);
        }
    }

    public static function patchProvider(): \Generator
    {
        yield 'added redis group in a new test fails' => [
            'tests/integration/Core/Framework/Adapter/Cache/RedisSomethingTest.php',
            'added',
            "+#[Group('redis')]\n+class RedisSomethingTest extends TestCase",
            true,
        ];

        yield 'added redis group in a modified test fails' => [
            'tests/integration/Core/Framework/Increment/RedisIncrementerTest.php',
            'modified',
            '+#[Group(\'redis\')]',
            true,
        ];

        yield 'removing the redis group is fine' => [
            'tests/integration/Core/Framework/Increment/RedisIncrementerTest.php',
            'modified',
            '-#[Group(\'redis\')]',
            false,
        ];

        yield 'other groups are fine' => [
            'tests/integration/Core/Framework/SomeTest.php',
            'modified',
            '+#[Group(\'slow\')]',
            false,
        ];

        yield 'redis group in a removed file is fine' => [
            'tests/integration/Core/Framework/Increment/RedisIncrementerTest.php',
            'removed',
            '+#[Group(\'redis\')]',
            false,
        ];

        yield 'redis group outside the tests tree is not this rule\'s business' => [
            'src/Core/DevOps/StaticAnalyze/Danger/Rules/RedisGroupUsage.php',
            'modified',
            '+#[Group(\'redis\')]',
            false,
        ];
    }
}
