/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-sales-channel-create', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
<div class="sw-page">
    <slot name="smart-bar-actions"></slot>
</div>
                `,
                },
                'sw-button-process': {
                    template: '<button class="sw-button-process"></button>',
                    props: ['disabled'],
                },
                'sw-language-switch': true,
                'sw-card-view': true,
                'sw-language-info': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
                'router-view': true,
                'sw-skeleton': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => ({}),
                        get: () =>
                            Promise.resolve({
                                productExports: {
                                    first: () => ({}),
                                },
                            }),
                        search: () => Promise.resolve([]),
                    }),
                },
                exportTemplateService: {
                    getProductExportTemplateRegistry: () => ({}),
                },
            },
            mocks: {
                $route: {
                    params: {
                        id: '1a2b3c4d',
                    },
                    name: '',
                },
            },
        },
    });
}

describe('src/module/sw-sales-channel/page/sw-sales-channel-create', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should disable the save button when privilege does not exists', async () => {
        const wrapper = await createWrapper();
        const saveButton = wrapper.getComponent('.sw-sales-channel-detail__save-action');

        await wrapper.setData({
            isLoading: false,
        });

        expect(saveButton.props('disabled')).toBe(true);
    });

    it('should enable the save button when privilege does exists', async () => {
        global.activeAclRoles = ['sales_channel.creator'];
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-sales-channel-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);
    });
});
