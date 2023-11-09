/**
 * @package buyers-experience
 */

import { shallowMount } from '@vue/test-utils';
import swSettingsSeo from 'src/module/sw-settings-seo/page/sw-settings-seo';
import 'src/app/component/structure/sw-page';
import 'src/app/component/structure/sw-card-view';
import swSystemConfig from 'src/module/sw-settings/component/sw-system-config';
import 'src/app/component/base/sw-card';

const classes = {
    root: 'sw-page__main-content',
    cardView: 'sw-card-view',
    templateCard: 'sw-seo-url-template-card',
    systemConfig: 'sw-system-config',
    settingsCard: 'sw-card',
};

Shopware.Component.register('sw-settings-seo', swSettingsSeo);
Shopware.Component.register('sw-system-config', swSystemConfig);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-settings-seo'), {
        stubs: {
            'sw-page': await Shopware.Component.build('sw-page'),
            'sw-icon': true,
            'sw-button': true,
            'sw-card-view': await Shopware.Component.build('sw-card-view'),
            'sw-seo-url-template-card': true,
            'sw-system-config': await Shopware.Component.build('sw-system-config'),
            'sw-search-bar': true,
            'sw-notification-center': true,
            'sw-help-center': true,
            'sw-card': await Shopware.Component.build('sw-card'),
            'sw-ignore-class': true,
            'sw-loader': true,
            'sw-app-actions': true,
            'sw-extension-component-section': true,
            'sw-skeleton': true,
            'sw-error-summary': true,
        },
        mocks: {
            $route: {
                meta: {
                },
                params: {
                    id: '',
                },
            },
        },
        provide: {
            systemConfigApiService: {
                getConfig: () => Promise.resolve({
                    'core.seo.redirectToCanonicalUrl': true,
                }),
            },

        },
    });
}

describe('src/module/sw-settings-seo/page/sw-settings-seo', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the settings card', async () => {
        await wrapper.vm.$nextTick();
        expect(
            wrapper.find(`.${classes.root}`)
                .find(`.${classes.cardView}`)
                .find(`.${classes.systemConfig}`)
                .find(`.${classes.settingsCard}`)
                .exists(),
        ).toBeTruthy();
    });
});
