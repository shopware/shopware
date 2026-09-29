<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoUnsafeRequestHasSessionRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoUnsafeRequestHasSessionRule>
 */
#[Package('framework')]
class NoUnsafeRequestHasSessionRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $message = 'Call Request::hasSession(true) instead of Request::hasSession(). Request::hasSession() itself does not initialize the lazy session, but it returns true for a lazy session factory. A later Request::getSession() initializes the session and can take the PHP session lock. Passive/read-only code, generic listeners, tracking, logging, background/admin-worker paths should use hasSession(true). Deliberate session-owning code may use Request::hasSession() only with a targeted @phpstan-ignore shopware.unsafeRequestHasSession comment that explains why initialization is intentional.';

        $this->analyse([__DIR__ . '/data/NoUnsafeRequestHasSessionRule/UnsafeRequestHasSessionUsage.php'], [
            [$message, 9],
            [$message, 14],
            [$message, 19],
            [$message, 24],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoUnsafeRequestHasSessionRule();
    }
}
