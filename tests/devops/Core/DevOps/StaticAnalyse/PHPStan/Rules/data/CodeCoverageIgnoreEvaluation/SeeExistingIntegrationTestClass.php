<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

use Shopware\Tests\Integration\Core\Framework\Webhook\Service\RelatedWebhooksTest;

/**
 * @codeCoverageIgnore
 *
 * @see RelatedWebhooksTest
 */
class SeeExistingIntegrationTestClass
{
    public function describe(int $age): string
    {
        if ($age >= 18) {
            return 'adult';
        }

        return 'minor';
    }
}
