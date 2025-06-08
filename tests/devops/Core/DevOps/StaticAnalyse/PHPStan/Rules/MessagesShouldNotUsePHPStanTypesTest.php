<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\MessagesShouldNotUsePHPStanTypes;

/**
 * @internal
 *
 * @extends  RuleTestCase<MessagesShouldNotUsePHPStanTypes>
 */
class MessagesShouldNotUsePHPStanTypesTest extends RuleTestCase
{
    public function testFailsOnAsyncMessageUsingPHPStanType(): void
    {
        $this->analyse(
            [__DIR__ . '/data/MessagesShouldNotUsePHPStanTypes/AsyncMessageUsingPHPStanType.php'],
            [
                [
                    'Messages should not use @phpstan-type annotations',
                    8,
                ],
            ]
        );
    }

    public function testFailsOnAsyncMessageImportingPHPStanType(): void
    {
        $this->analyse(
            [__DIR__ . '/data/MessagesShouldNotUsePHPStanTypes/AsyncMessageImportingPHPStanType.php'],
            [
                [
                    'Messages should not use @phpstan-import-type annotations',
                    8,
                ],
            ]
        );
    }

    public function testPassesOnAsyncMessageUsingNativeTypes(): void
    {
        $this->analyse([__DIR__ . '/data/MessagesShouldNotUsePHPStanTypes/AsyncMessageUsingNativeTypes.php'], []);
    }

    public function testPassesOnAsyncMessageNotUsingPHPStanType(): void
    {
        $this->analyse([__DIR__ . '/data/MessagesShouldNotUsePHPStanTypes/AsyncMessageNotUsingPHPStanType.php'], []);
    }

    public function testPassesOnRegularClassUsingPHPStanType(): void
    {
        $this->analyse([__DIR__ . '/data/MessagesShouldNotUsePHPStanTypes/RegularClassUsingPHPStanType.php'], []);
    }

    public function testPassesOnRegularClassImportingPHPStanType(): void
    {
        $this->analyse([__DIR__ . '/data/MessagesShouldNotUsePHPStanTypes/RegularClassImportingPHPStanType.php'], []);
    }

    protected function getRule(): Rule
    {
        return new MessagesShouldNotUsePHPStanTypes();
    }
}
