import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-promotion/view/sw-promotion-detail-discounts';
import promotionState from 'src/module/sw-promotion/page/sw-promotion-detail/state';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-promotion-detail-discounts'), {
        localVue,
        stubs: {
            'sw-card': true,
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
                    search: () => Promise.resolve([]),
                    get: () => Promise.resolve([]),
                    create: () => {}
                })
            }
        },
        mocks: {
            $tc: v => v,
            $route: {
                query: ''
            },
            $router: {
                replace: () => {}
            }
        }
    });
}

describe('src/module/sw-promotion/view/sw-promotion-detail-discounts', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swPromotionDetail', promotionState);
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable adding discounts when privileges not set', async () => {
        const wrapper = createWrapper();

        const element = wrapper.find('sw-button-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes().disabled).toBeTruthy();
    });

    it('should enable adding discounts when privilege is set', async () => {
        const wrapper = createWrapper([
            'promotion.editor'
        ]);

        const element = wrapper.find('sw-button-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes().disabled).toBeFalsy();
    });
});
