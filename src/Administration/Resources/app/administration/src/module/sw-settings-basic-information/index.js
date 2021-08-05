import CaptchaService from './service/captcha.service';
import './page/sw-settings-basic-information';
import './component/sw-settings-captcha-select';
import './component/sw-settings-captcha-select-v2';

const { Module } = Shopware;

Shopware.Service().register('captchaService', () => {
    return new CaptchaService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service().get('loginService'),
    );
});

Module.register('sw-settings-basic-information', {
    type: 'core',
    name: 'settings-basic-information',
    title: 'sw-settings-basic-information.general.mainMenuItemGeneral',
    description: 'sw-settings-basic-information.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
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
        icon: 'default-basic-stack-line',
        privilege: 'system.system_config',
    },
});
