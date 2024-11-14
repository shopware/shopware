/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-settings-language-list', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        params: {
                            sortBy: 'sortBy',
                        },
                        query: {
                            page: 1,
                            limit: 25,
                        },
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve([
                                    {
                                        name: 'English',
                                    },
                                    {
                                        name: 'German',
                                    },
                                    {
                                        name: 'Vietnamese',
                                    },
                                ]);
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

                    detailPageLinkText(allowEdit) {
                        return allowEdit ? this.$tc('global.default.edit') : this.$tc('global.default.view');
                    },

                    searchRankingService: {},
                },
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `,
                    },
                    'sw-switch-field': true,
                    'sw-search-bar': true,
                    'sw-language-switch': true,
                    'sw-icon': true,
                    'sw-button': true,
                    'sw-sidebar': true,
                    'sw-sidebar-item': true,
                    'sw-collapse': true,
                    'sw-context-menu-item': true,
                    'sw-entity-listing': {
                        inject: ['detailPageLinkText'],
                        props: [
                            'items',
                            'allowEdit',
                            'allowView',
                            'detailRoute',
                        ],
                        template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="detail-action" v-bind="{ item }">
                                <sw-context-menu-item
                                    v-if="detailRoute"
                                    :disabled="!allowEdit && !allowView || undefined"
                                    class="sw-entity-listing__context-menu-edit-action">
                                    {{ detailPageLinkText(allowEdit) }}
                                </sw-context-menu-item>
                            </slot>
                            <slot name="delete-action" v-bind="{ item }"></slot>
                        </template>
                    </div>
                `,
                    },
                    'sw-text-field': true,
                    'router-link': true,
                },
            },
        },
    );
}

describe('module/sw-settings-language/page/sw-settings-language-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to create a new language', async () => {
        const wrapper = await createWrapper([
            'language.creator',
        ]);
        await flushPromises();

        const addButton = wrapper.find('.sw-settings-language-list__button-create');

        expect(addButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const addButton = wrapper.find('.sw-settings-language-list__button-create');

        expect(addButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to view a language', async () => {
        const wrapper = await createWrapper([
            'language.viewer',
        ]);
        await flushPromises();

        const elementItemAction = wrapper.find('.sw-entity-listing__context-menu-edit-action');

        expect(elementItemAction.attributes().disabled).toBeFalsy();
        expect(elementItemAction.text()).toBe('global.default.view');
    });

    it('should be able to edit a language', async () => {
        const wrapper = await createWrapper([
            'language.editor',
        ]);
        await flushPromises();

        const elementItemAction = wrapper.find('.sw-entity-listing__context-menu-edit-action');

        expect(elementItemAction.attributes().disabled).toBeFalsy();
        expect(elementItemAction.text()).toBe('global.default.edit');
    });

    it('should not be able to edit a language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const elementItemAction = wrapper.find('.sw-entity-listing__context-menu-edit-action');

        expect(elementItemAction.attributes().disabled).toBeTruthy();
        expect(elementItemAction.text()).toBe('global.default.view');
    });

    it('should be able to delete a language', async () => {
        const wrapper = await createWrapper([
            'language.deleter',
        ]);
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-settings-language-list__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete a language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-settings-language-list__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to inline edit a language', async () => {
        const wrapper = await createWrapper([
            'language.editor',
        ]);
        await flushPromises();

        const entityListing = wrapper.find('.sw-settings-language-list-grid');

        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to inline edit a language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const entityListing = wrapper.find('.sw-settings-language-list-grid');

        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should contain a listing criteria with correct properties', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.listingCriteria).toEqual(
            expect.objectContaining({
                associations: expect.arrayContaining([
                    expect.objectContaining({
                        association: 'translationCode',
                    }),
                ]),
            }),
        );
    });
});
