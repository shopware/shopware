import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-promotion/page/sw-promotion-list';

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 * @feature-deprecated (flag:FEATURE_NEXT_13810)
 */
function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-promotion-list'), {
        stubs: {
            'sw-page': {
                template: '<div class="sw-page"><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>'
            },
            'sw-button': true,
            'sw-entity-listing': true,
            'sw-empty-state': true
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                }
            },
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([]),
                    get: () => Promise.resolve([]),
                    create: () => {}
                })
            }
        },
        mocks: {
            $route: {
                query: ''
            }
        }
    });
}

describe('src/module/sw-promotion/page/sw-promotion-list', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable create button when privilege not available', async () => {
        const wrapper = createWrapper();

        const element = wrapper.find('.sw-promotion-list__button-add-promotion');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes().disabled).toBeTruthy();
    });

    it('should enable create button when privilege available', async () => {
        const wrapper = createWrapper([
            'promotion.creator'
        ]);

        const element = wrapper.find('.sw-promotion-list__button-add-promotion');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes().disabled).toBeUndefined();
    });

    it('should disable editing of entries when privilege not set', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoading: false
        });

        const element = wrapper.find('sw-entity-listing-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes()['allow-edit']).toBeUndefined();
        expect(element.attributes()['allow-view']).toBeUndefined();
        expect(element.attributes()['show-selection']).toBeUndefined();
        expect(element.attributes()['allow-inline-edit']).toBeUndefined();
    });

    it('should enable editing of entries when privilege is set', async () => {
        const wrapper = createWrapper([
            'promotion.viewer',
            'promotion.editor'
        ]);

        await wrapper.setData({
            isLoading: false
        });

        const element = wrapper.find('sw-entity-listing-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes()['allow-edit']).toBeTruthy();
        expect(element.attributes()['allow-view']).toBeTruthy();
        expect(element.attributes()['show-selection']).toBeUndefined();
        expect(element.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should enable deletion of entries when privilege is set', async () => {
        const wrapper = createWrapper([
            'promotion.viewer',
            'promotion.editor',
            'promotion.deleter'
        ]);

        await wrapper.setData({
            isLoading: false
        });

        const element = wrapper.find('sw-entity-listing-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes()['allow-edit']).toBeTruthy();
        expect(element.attributes()['allow-view']).toBeTruthy();
        expect(element.attributes()['show-selection']).toBeTruthy();
        expect(element.attributes()['allow-inline-edit']).toBeTruthy();
    });
});
