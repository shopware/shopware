import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-mail-template/component/sw-mail-template-list';

const createWrapper = (privileges = []) => {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-mail-template-list'), {
        localVue,
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve([
                            {
                                id: '123',
                                description: 'Shopware Default Template',
                                mailTemplateType: {
                                    id: '1',
                                    name: 'Enter delivery state: Returned'
                                },
                                salesChannels: [
                                    {
                                        salesChannel: {
                                            name: 'Storefront'
                                        }
                                    }
                                ]
                            }
                        ]);
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        },
        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            }
        },
        stubs: {
            'sw-card': {
                template: '<div><slot name="grid"></slot></div>'
            },
            'sw-entity-listing': {
                props: ['items', 'allowEdit', 'allowView', 'allowDelete', 'detailRoute'],
                template: `
                    <div id="mailTemplateGrid">
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }">
                                <slot name="detail-action" v-bind="{ item }">
                                    <sw-context-menu-item-stub class="sw-entity-listing__context-menu-edit-action"
                                                               v-if="detailRoute"
                                                               :disabled="!allowEdit && !allowView"
                                                               :routerLink="{ name: detailRoute, params: { id: item.id } }">
                                        {{ !allowEdit && allowView ? 'global.default.view' : 'global.default.edit' }}
                                    </sw-context-menu-item-stub>
                                </slot>
                                <slot name="more-actions" v-bind="{ item }"></slot>
                                <slot name="delete-action" v-bind="{ item }">
                                    <sw-context-menu-item-stub :disabled="!allowDelete"
                                                               class="sw-entity-listing__context-menu-edit-delete">
                                    </sw-context-menu-item-stub>
                                </slot>
                            </slot>
                        </template>
                    </div>`
            },
            'sw-context-menu-item': true
        }
    });
};

describe('modules/sw-mail-template/component/sw-mail-template-list', () => {
    it('should not allow to duplicate without create permission', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const duplicateButton = wrapper.find('.sw-mail-template-list-grid__duplicate-action');
        expect(duplicateButton.attributes().disabled).toBeTruthy();
    });

    it('should allow to duplicate with create permission', async () => {
        const wrapper = createWrapper(['mail_templates.creator']);
        await wrapper.vm.$nextTick();

        const duplicateButton = wrapper.find('.sw-mail-template-list-grid__duplicate-action');
        expect(duplicateButton.attributes().disabled).toBeFalsy();
    });

    it('should not allow to delete without delete permission', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const deleteButton = wrapper.find('.sw-entity-listing__context-menu-edit-delete');
        expect(deleteButton.attributes().disabled).toBeTruthy();
    });

    it('should allow to delete with delete permission', async () => {
        const wrapper = createWrapper(['mail_templates.deleter']);
        await wrapper.vm.$nextTick();

        const deleteButton = wrapper.find('.sw-entity-listing__context-menu-edit-delete');
        expect(deleteButton.attributes().disabled).toBeFalsy();
    });

    it('should not allow to edit without edit permission', async () => {
        const wrapper = createWrapper(['mail_templates.viewer']);
        await wrapper.vm.$nextTick();

        const editButton = wrapper.find('.sw-entity-listing__context-menu-edit-action');
        expect(editButton.text()).toEqual('global.default.view');
    });

    it('should allow to edit with edit permission', async () => {
        const wrapper = createWrapper([
            'mail_templates.viewer',
            'mail_templates.editor'
        ]);
        await wrapper.vm.$nextTick();

        const editButton = wrapper.find('.sw-entity-listing__context-menu-edit-action');
        expect(editButton.text()).toEqual('global.default.edit');
    });

    it('should hide item selection if user does not have delete permission', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-mail-templates-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['show-selection']).toBeFalsy();
    });

    it('should show item selection if user has delete permission', async () => {
        const wrapper = createWrapper(['mail_templates.deleter']);
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-mail-templates-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['show-selection']).toBeTruthy();
    });

    it('should return three skeletons when there are no mail templates', () => {
        const wrapper = createWrapper();
        const amountOfSkeletons = wrapper.vm.skeletonItemAmount;

        expect(amountOfSkeletons).toBe(3);
    });

    it('should return the same amount of skeletons as there are mail templates', () => {
        const wrapper = createWrapper();

        // fill listing with mail templates mocks
        wrapper.vm.mailTemplates = [
            { type: 'contact_form', salesChannel: 'Headless' },
            { type: 'password_recovery', salesChannel: 'Storefront' }
        ];

        const amountOfSkeletons = wrapper.vm.skeletonItemAmount;
        expect(amountOfSkeletons).toBe(2);
    });

    it('should show the listing when there are more than zero mail templates', async () => {
        const wrapper = createWrapper();

        // wait for vue to fetch data and render the listing
        await wrapper.vm.$nextTick();

        const isListingVisible = wrapper.vm.showListing;
        expect(isListingVisible).toBe(true);

        const listing = wrapper.find('#mailTemplateGrid');

        expect(listing.exists()).toBe(true);
    });

    it('should hide mail templates when there are no mail templates', async () => {
        const wrapper = createWrapper();
        // wait for vue to render the listing
        await wrapper.vm.$nextTick();

        wrapper.vm.mailTemplates = [];

        // wait for vue to remove the grid
        await wrapper.vm.$nextTick();

        const isListingVisible = wrapper.vm.showListing;
        expect(isListingVisible).toBe(false);

        const listing = wrapper.find('#mailTemplateGrid');
        expect(listing.exists()).toBe(false);
    });
});
