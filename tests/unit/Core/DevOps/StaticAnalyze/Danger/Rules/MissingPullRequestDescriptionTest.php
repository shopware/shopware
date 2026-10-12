<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\MissingPullRequestDescription;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MissingPullRequestDescription::class)]
class MissingPullRequestDescriptionTest extends TestCase
{
    private const TEMPLATE_PATH = __DIR__ . '/../../../../../../../.github/PULL_REQUEST_TEMPLATE.md';

    #[TestDox('Fails on an empty, template-only or too short description and passes a real one')]
    #[DataProvider('descriptionProvider')]
    public function testDescription(string $body, ?string $expectedFailure): void
    {
        $context = $this->runRule($body);

        if ($expectedFailure === null) {
            static::assertFalse($context->hasFailures(), implode("\n", $context->getFailures()));

            return;
        }

        static::assertCount(1, $context->getFailures());
        static::assertStringContainsString($expectedFailure, $context->getFailures()[0]);
    }

    public static function descriptionProvider(): \Generator
    {
        yield 'empty body' => ['', 'has no description'];
        yield 'whitespace only' => ["  \n\n\t", 'has no description'];
        yield 'untouched template' => [self::template(), 'has no description'];
        yield 'template with only ticked checkboxes' => [
            str_replace('- [ ]', '- [x]', self::template()),
            'has no description',
        ];
        yield 'only an html comment' => ['<!-- a multi line
comment that is long enough on its own but is not authored text -->', 'has no description'];
        yield 'too short' => ['Fix typo.', 'too short (9 of at least 50 characters)'];
        yield 'too short spread over template sections' => [
            str_replace('### 1. Why is this change necessary?', "### 1. Why is this change necessary?\nBug.", self::template()),
            'too short (4 of at least 50 characters)',
        ];
        yield 'exactly the minimum length' => [str_repeat('a', MissingPullRequestDescription::MIN_LENGTH), null];
        yield 'filled template' => [
            str_replace(
                ['### 1. Why is this change necessary?', '### 2. What does this change do, exactly?'],
                [
                    "### 1. Why is this change necessary?\nThe cart total was wrong for bundles.",
                    "### 2. What does this change do, exactly?\nIt sums the bundle items before applying the discount.",
                ],
                self::template()
            ),
            null,
        ];
        yield 'free-form description without the template' => [
            "The cart total was wrong for bundles.\n\nThis change sums the bundle items before applying the discount.",
            null,
        ];
    }

    #[TestDox('Counts every line when the template file is missing on the target branch')]
    public function testMissingTemplateFile(): void
    {
        $pullRequest = new StubPullRequest();
        $pullRequest->body = self::template();
        $context = new Context(new StubPlatform($pullRequest));

        (new MissingPullRequestDescription(__DIR__ . '/does-not-exist.md'))($context);

        static::assertFalse($context->hasFailures());
    }

    private function runRule(string $body): Context
    {
        $pullRequest = new StubPullRequest();
        $pullRequest->body = $body;
        $context = new Context(new StubPlatform($pullRequest));

        (new MissingPullRequestDescription(self::TEMPLATE_PATH))($context);

        return $context;
    }

    private static function template(): string
    {
        return (string) file_get_contents(self::TEMPLATE_PATH);
    }
}
