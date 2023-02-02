import CaptchaService from './service/captcha.service';

const { Module } = Shopware;

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-basic-information', () => import('./page/sw-settings-basic-information'));
Shopware.Component.register('sw-settings-captcha-select-v2', () => import('./component/sw-settings-captcha-select-v2'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('captchaService', () => {
    return new CaptchaService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service().get('loginService'),
    );
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-basic-information', {
    type: 'core',
    name: 'settings-basic-information',
    title: 'sw-settings-basic-information.general.mainMenuItemGeneral',
    description: 'sw-settings-basic-information.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-basic-information',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.basic.information.index',
        icon: 'regular-bars',
        privilege: 'system.system_config',
    },
});
