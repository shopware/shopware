/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-promotion-detail-discounts', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-card': true,
                    'sw-promotion-discount-component': true,
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
        },
    );
}

describe('src/module/sw-promotion-v2/view/sw-promotion-detail-discounts', () => {
    it('should disable adding discounts when privileges not set', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        expect(
            wrapper.findByText('button', 'sw-promotion.detail.main.discounts.buttonAddDiscount').attributes('disabled'),
        ).toBeDefined();
    });

    it('should enable adding discounts when privilege is set', async () => {
        global.activeAclRoles = ['promotion.editor'];

        const wrapper = await createWrapper();

        expect(
            wrapper.findByText('button', 'sw-promotion.detail.main.discounts.buttonAddDiscount').attributes('disabled'),
        ).toBeUndefined();
    });
});
