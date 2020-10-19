import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-seo/page/sw-settings-seo';
import 'src/app/component/structure/sw-page';
import 'src/app/component/structure/sw-card-view';
import 'src/module/sw-settings/component/sw-system-config';
import 'src/app/component/base/sw-card';

const classes = {
    root: 'sw-page__main-content',
    cardView: 'sw-card-view',
    templateCard: 'sw-seo-url-template-card',
    systemConfig: 'sw-system-config',
    settingsCard: 'sw-card'
};

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-settings-seo'), {
        stubs: {
            'sw-page': Shopware.Component.build('sw-page'),
            'sw-icon': true,
            'sw-button': true,
            'sw-card-view': Shopware.Component.build('sw-card-view'),
            'sw-seo-url-template-card': true,
            'sw-system-config': Shopware.Component.build('sw-system-config'),
            'sw-search-bar': true,
            'sw-notification-center': true,
            'sw-card': Shopware.Component.build('sw-card'),
            'sw-loader': true
        },
        mocks: {
            $tc: v => v,
            $route: {
                meta: {
                },
                params: {
                    id: ''
                }
            }
        },
        provide: {
            systemConfigApiService: {
                getConfig: () => Promise.resolve({
                    'core.seo.redirectToCanonicalUrl': true
                })
            },
            feature: {
                isActive: () => true
            }
        }
    });
}

describe('src/module/sw-settings-seo/page/sw-settings-seo', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the settings card', () => {
        expect(
            wrapper.find(`.${classes.root}`)
                .find(`.${classes.cardView}`)
                .find(`.${classes.systemConfig}`)
                .find(`.${classes.settingsCard}`)
                .exists()
        ).toBeTruthy();
    });
});
