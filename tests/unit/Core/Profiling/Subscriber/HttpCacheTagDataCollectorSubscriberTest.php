<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Profiling\Subscriber\HttpCacheTagDataCollectorSubscriber;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(HttpCacheTagDataCollectorSubscriber::class)]
class HttpCacheTagDataCollectorSubscriberTest extends TestCase
{
    public function testLateCollectSameTagInValidAndInvalidURIs(): void
    {
        HttpCacheTagDataCollectorSubscriber::$tags = [
            'n/a' => [
                'system.config-' => [
                    'SystemConfigService::get | CanonicalRedirectService::getRedirect' => 1,
                ],
            ],
            '/widgets/checkout/info' => [
                'system.config-' => [
                    'SystemConfigService::get | StorefrontSubscriber::shouldRenewToken' => 1,
                ],
            ],
        ];

        $data = $this->getProcessedTags();
        static::assertSame([
            '/widgets/checkout/info' => [
                'system.config-' => [
                    'SystemConfigService::get | StorefrontSubscriber::shouldRenewToken' => 1,
                    'SystemConfigService::get | CanonicalRedirectService::getRedirect' => 1,
                ],
            ],
        ], $data);
    }

    public function testLateCollectWithDifferentTagsInValidAndInvalidURIs(): void
    {
        HttpCacheTagDataCollectorSubscriber::$tags = [
            'n/a' => [
                'system.config-' => [
                    'SystemConfigService::get | CanonicalRedirectService::getRedirect' => 1,
                ],
            ],
            '/api/rule' => [
                'translator-01956aa1d58f729d8f7cb8643a16c708' => [
                    'Translator::trans | DataCollectorTranslator::trans' => 1,
                ],
            ],
        ];

        $data = $this->getProcessedTags();
        static::assertSame([
            '/api/rule' => [
                'translator-01956aa1d58f729d8f7cb8643a16c708' => [
                    'Translator::trans | DataCollectorTranslator::trans' => 1,
                ],
                'system.config-' => [
                    'SystemConfigService::get | CanonicalRedirectService::getRedirect' => 1,
                ],
            ],
        ], $data);
    }

    /**
     * @return array<string, array<string, array<string, int>>>
     */
    private function getProcessedTags(): array
    {
        $subscriber = new HttpCacheTagDataCollectorSubscriber(new RequestStack());

        $subscriber->lateCollect();

        return $subscriber->getData();
    }
}
