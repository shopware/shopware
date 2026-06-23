<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

use Shopware\Tests\Integration\Core\Framework\Webhook\Service\RelatedWebhooksTest;

class MethodSeeIntegrationTestClass
{
    /**
     * @codeCoverageIgnore
     *
     * @see RelatedWebhooksTest
     */
    public function describe(int $age): string
    {
        if ($age >= 18) {
            return 'adult';
        }

        return 'minor';
    }
}
