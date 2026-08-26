<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use GuzzleHttp\Client;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha\BasicCaptchaGenerator;
use Shopware\Storefront\Framework\Captcha\CaptchaCookieCollectListener;
use Shopware\Storefront\Framework\Captcha\CaptchaRouteListener;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV2;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Shopware\Storefront\Framework\Captcha\HoneypotCaptcha;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CaptchaRouteListener::class)
        ->args([
            tagged_iterator('shopware.storefront.captcha'),
            service(SystemConfigService::class),
            service('service_container'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(HoneypotCaptcha::class)
        ->args([
            service('validator'),
        ])
        ->tag('shopware.storefront.captcha', ['priority' => 400]);

    $services->set(BasicCaptcha::class)
        ->args([
            service('request_stack'),
            service(SystemConfigService::class),
        ])
        ->tag('shopware.storefront.captcha', ['priority' => 300]);

    $services->set(BasicCaptchaGenerator::class);

    $services->set('shopware.captcha.client', Client::class);

    $services->set(GoogleReCaptchaV2::class)
        ->args([
            service('shopware.captcha.client'),
        ])
        ->tag('shopware.storefront.captcha', ['priority' => 200]);

    $services->set(GoogleReCaptchaV3::class)
        ->args([
            service('shopware.captcha.client'),
        ])
        ->tag('shopware.storefront.captcha', ['priority' => 100]);

    $services->set(CaptchaCookieCollectListener::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_listener');
};
