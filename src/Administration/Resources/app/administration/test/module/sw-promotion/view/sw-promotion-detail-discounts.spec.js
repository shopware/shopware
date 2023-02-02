import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-promotion/view/sw-promotion-detail-discounts';
import promotionState from 'src/module/sw-promotion/page/sw-promotion-detail/state';

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 * @feature-deprecated (flag:FEATURE_NEXT_13810)
 */
function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-promotion-detail-discounts'), {
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
            $route: {
                query: ''
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
