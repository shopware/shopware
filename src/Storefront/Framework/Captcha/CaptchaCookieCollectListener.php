<?php

declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
class CaptchaCookieCollectListener
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        $salesChannelId = $event->salesChannelContext->getSalesChannelId();
        $googleRecaptchaActive = $this->systemConfigService->getBool(
            'core.basicInformation.activeCaptchasV2.' . GoogleReCaptchaV2::CAPTCHA_NAME . '.isActive',
            $salesChannelId
        ) || $this->systemConfigService->getBool(
            'core.basicInformation.activeCaptchasV2.' . GoogleReCaptchaV3::CAPTCHA_NAME . '.isActive',
            $salesChannelId
        );

        if (!$googleRecaptchaActive) {
            return;
        }

        $requiredCookieGroup = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        if (!$requiredCookieGroup || !$requiredCookieGroup->isRequired) {
            return;
        }

        $entries = $requiredCookieGroup->getEntries();
        if ($entries === null) {
            $entries = new CookieEntryCollection();
            $requiredCookieGroup->setEntries($entries);
        }

        $entryRequiredCaptcha = new CookieEntry('_GRECAPTCHA');
        $entryRequiredCaptcha->name = 'cookie.groupRequiredCaptcha';
        $entryRequiredCaptcha->value = '1';

        $entries->add($entryRequiredCaptcha);
    }
}
