/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-sales-channel-detail-analytics', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-card': {
                        template: '<div class="sw-card"><slot></slot></div>',
                    },
                    'sw-switch-field': {
                        template: '<div class="sw-field sw-switch-field"></div>',
                        props: ['disabled'],
                    },
                    'sw-text-field': {
                        template: '<div class="sw-field sw-text-field"></div>',
                        props: ['disabled'],
                    },
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                        props: ['disabled'],
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            create: () => ({}),
                        }),
                    },
                },
            },
            props: {
                salesChannel: {},
            },
        },
    );
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-analytics', () => {
    it('should have fields disabled when the user has no privileges', async () => {
        const wrapper = await createWrapper();

        const fields = wrapper.findAllComponents('.sw-field');

        expect(fields.length).toBeGreaterThan(0);
        fields.forEach((field) => {
            expect(field.props('disabled')).toBe(true);
        });
    });

    it('should have fields enabled when the user has privileges', async () => {
        global.activeAclRoles = ['sales_channel.editor'];

        const wrapper = await createWrapper();

        const fields = wrapper.findAllComponents('.sw-field');

        expect(fields.length).toBeGreaterThan(0);
        fields.forEach((field) => {
            expect(field.props('disabled')).toBe(false);
        });
    });
});
