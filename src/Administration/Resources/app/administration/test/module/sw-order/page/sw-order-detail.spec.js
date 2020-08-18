import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/page/sw-order-detail';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-order-detail'), {
        localVue,
        stubs: {
            'sw-page': '<div><slot name="smart-bar-actions"></slot></div>',
            'sw-button': true
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
                    search: () => Promise.resolve([])
                })
            }
        },
        mocks: {
            $tc: v => v,
            $route: {
                params: {
                    id: '1a2b3c'
                }
            }
        }
    });
}

describe('src/module/sw-order/page/sw-order-detail', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should have an disabled edit button', () => {
        wrapper.setData({ isLoading: false });

        const editButton = wrapper.find('.sw-order-detail__smart-bar-edit-button');

        expect(editButton.attributes().disabled).toBe('true');
    });

    it('should have an enabled edit button', () => {
        wrapper = createWrapper(['order.editor']);
        wrapper.setData({ isLoading: false });

        const editButton = wrapper.find('.sw-order-detail__smart-bar-edit-button');

        expect(editButton.attributes().disabled).toBeUndefined();
    });
});
