import { mount } from '@vue/test-utils';
import promotionState from 'src/module/sw-promotion-v2/page/sw-promotion-v2-detail/state';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-promotion-detail-discounts', { sync: true }), {
        global: {
            stubs: {
                'sw-card': true,
                'sw-button': {
                    template: '<button class="sw-button"><slot></slot></button>',
                    props: ['disabled'],
                },
            },
            provide: {
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
        },
    });
}

describe('src/module/sw-promotion-v2/view/sw-promotion-detail-discounts', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swPromotionDetail', promotionState);
    });

    it('should disable adding discounts when privileges not set', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        expect(wrapper.getComponent('.sw-button').props('disabled')).toBe(true);
    });

    it('should enable adding discounts when privilege is set', async () => {
        global.activeAclRoles = ['promotion.editor'];

        const wrapper = await createWrapper();

        expect(wrapper.getComponent('.sw-button').props('disabled')).toBe(false);
    });
});
