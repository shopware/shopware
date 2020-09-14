import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/page/sw-order-list';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-order-list'), {
        localVue,
        stubs: {
            'sw-page': {
                template: '<div><slot name="smart-bar-actions"></slot></div>'
            },
            'sw-button': true
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                }
            },
            stateStyleDataProviderService: {},
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve([]) })
            }
        },
        mocks: {
            $tc: v => v,
            $route: { query: '' },
            $router: { replace: () => {} }
        }
    });
}

describe('src/module/sw-order/page/sw-order-list', () => {
    let wrapper;
    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled add button', async () => {
        const addButton = wrapper.find('.sw-order-list__add-order');

        expect(addButton.attributes().disabled).toBe('true');
    });

    it('should have an disabled add button', async () => {
        wrapper = createWrapper(['order.creator']);
        const addButton = wrapper.find('.sw-order-list__add-order');

        expect(addButton.attributes().disabled).toBeUndefined();
    });
});
