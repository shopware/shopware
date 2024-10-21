/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

const mailHeaderFooterMock = [
    {
        id: '1',
        description: 'Shopware Default Template',
        name: 'Order Header',
        salesChannels: [
            {
                name: 'Storefront',
            },
        ],
    },
    {
        id: '2',
        description: 'Shopware Default Template',
        name: 'Order Header',
        salesChannels: [],
    },
];

const createWrapper = async (privileges = []) => {
    return mount(await wrapTestComponent('sw-mail-header-footer-list', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve(mailHeaderFooterMock);
                        },

                        delete: (id) => {
                            const hasSalesChannel = mailHeaderFooterMock.find((item) => item.id === id).salesChannels.length;

                            if (hasSalesChannel) {
                                return Promise.reject();
                            }

                            return Promise.resolve();
                        },
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                searchRankingService: {},
            },
            mocks: {
                $route: {
                    query: {
                        page: 1,
                        limit: 25,
                    },
                },
            },
            stubs: {
                'sw-card': {
                    template: '<div><slot name="grid"></slot></div>',
                },
                'sw-entity-listing': {
                    props: [
                        'items',
                        'allowEdit',
                        'allowView',
                        'allowDelete',
                        'detailRoute',
                    ],
                    template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }">
                                <slot name="detail-action" v-bind="{ item }">
                                    <div class="sw-entity-listing__context-menu-edit-action"
                                         v-if="detailRoute"
                                         :disabled="!allowEdit && !allowView"
                                         :routerLink="{ name: detailRoute, params: { id: item.id } }">
                                        {{ !allowEdit && allowView ? 'global.default.view' : 'global.default.edit' }}
                                    </div>
                                </slot>
                                <slot name="more-actions" v-bind="{ item }"></slot>
                                <slot name="delete-action" v-bind="{ item }">
                                    <div :disabled="!allowDelete || undefined"
                                         class="sw-entity-listing__context-menu-edit-delete">
                                    </div>
                                </slot>
                            </slot>
                        </template>
                    </div>`,
                    methods: {
                        resetSelection() {},
                        doSearch() {},
                    },
                },
                'sw-context-menu-item': true,
                'sw-empty-state': true,
                'sw-button': true,
            },
        },
    });
};

describe('modules/sw-mail-template/component/sw-mail-header-footer-list', () => {
    it('should not allow to duplicate without create permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const duplicateButton = wrapper.find('.sw-mail-header-footer-list-grid__duplicate-action');
        expect(duplicateButton.attributes().disabled).toBeTruthy();
    });

    it('should allow to duplicate with create permission', async () => {
        const wrapper = await createWrapper(['mail_templates.creator']);
        await flushPromises();

        const duplicateButton = wrapper.find('.sw-mail-header-footer-list-grid__duplicate-action');
        expect(duplicateButton.attributes().disabled).toBeFalsy();
    });

    it('should not allow to delete without delete permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const deleteButton = wrapper.find('.sw-entity-listing__context-menu-edit-delete');
        expect(deleteButton.attributes().disabled).toBeTruthy();
    });

    it('should allow to delete with delete permission', async () => {
        const wrapper = await createWrapper(['mail_templates.deleter']);
        await flushPromises();

        const deleteButton = wrapper.find('.sw-entity-listing__context-menu-edit-delete');
        expect(deleteButton.attributes().disabled).toBeFalsy();
    });

    it('should not allow to edit without edit permission', async () => {
        const wrapper = await createWrapper(['mail_templates.viewer']);
        await flushPromises();

        const editButton = wrapper.find('.sw-entity-listing__context-menu-edit-action');
        expect(editButton.text()).toBe('global.default.view');
    });

    it('should allow to edit with edit permission', async () => {
        const wrapper = await createWrapper([
            'mail_templates.viewer',
            'mail_templates.editor',
        ]);
        await flushPromises();

        const editButton = wrapper.find('.sw-entity-listing__context-menu-edit-action');
        expect(editButton.text()).toBe('global.default.edit');
    });

    it('should hide item selection if user does not have delete permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const entityList = wrapper.find('.sw-mail-templates-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['show-selection']).toBeFalsy();
    });

    it('should show item selection if user has delete permission', async () => {
        const wrapper = await createWrapper(['mail_templates.deleter']);
        await flushPromises();

        const entityList = wrapper.find('.sw-mail-templates-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['show-selection']).toBeTruthy();
    });

    it('should show error notification if delete failed', async () => {
        const wrapper = await createWrapper(['mail_templates.deleter']);
        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();

        await expect(wrapper.vm.onDelete(mailHeaderFooterMock[0])).rejects.toEqual();
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should not show error notification if delete successfully', async () => {
        const wrapper = await createWrapper(['mail_templates.deleter']);
        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();

        wrapper.vm.onDelete(mailHeaderFooterMock[1]);
        await flushPromises();

        expect(wrapper.vm.createNotificationError).not.toHaveBeenCalled();

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should show the listing when there are more than zero mail templates', async () => {
        const wrapper = await createWrapper();

        // wait for vue to render the listing
        await flushPromises();

        // fill listing with mail templates mocks
        wrapper.vm.mailHeaderFooters = [
            { type: 'contact_form', salesChannel: 'Headless' },
            { type: 'password_recovery', salesChannel: 'Storefront' },
        ];

        // wait for vue to update the grid
        await flushPromises();

        const listing = wrapper.find('#mailHeaderFooterGrid');
        expect(listing.exists()).toBe(true);

        const isListingVisible = wrapper.vm.showListing;
        expect(isListingVisible).toBe(true);
    });

    it('should hide mail templates when there are no mail templates', async () => {
        const wrapper = await createWrapper();

        // wait for vue to render the listing
        await flushPromises();

        wrapper.vm.mailHeaderFooters = [];

        // wait for vue to remove the grid
        await flushPromises();

        const listing = wrapper.find('#mailHeaderFooterGrid');
        expect(listing.exists()).toBe(false);

        const isListingVisible = wrapper.vm.showListing;
        expect(isListingVisible).toBe(false);
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });
});
