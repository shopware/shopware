/**
 * @package buyers-experience
 */
import { shallowMount } from '@vue/test-utils';
import swPromotionDetailDiscounts from 'src/module/sw-promotion-v2/view/sw-promotion-detail-discounts';
import promotionState from 'src/module/sw-promotion-v2/page/sw-promotion-v2-detail/state';

Shopware.Component.register('sw-promotion-detail-discounts', swPromotionDetailDiscounts);

async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-promotion-detail-discounts'), {
        stubs: {
            'sw-card': true,
            'sw-button': true,
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                },
            },
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([]),
                    get: () => Promise.resolve([]),
                    create: () => {},
                }),
            },
        },
        mocks: {
            $route: {
                query: '',
            },
        },
    });
}

describe('src/module/sw-promotion-v2/view/sw-promotion-detail-discounts', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swPromotionDetail', promotionState);
    });

    it('should disable adding discounts when privileges not set', async () => {
        const wrapper = await createWrapper();

        const element = wrapper.find('sw-button-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes().disabled).toBeTruthy();
    });

    it('should enable adding discounts when privilege is set', async () => {
        const wrapper = await createWrapper([
            'promotion.editor',
        ]);

        const element = wrapper.find('sw-button-stub');

        expect(element.exists()).toBeTruthy();
        expect(element.attributes().disabled).toBeFalsy();
    });
});
