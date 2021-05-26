import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-salutation/page/sw-settings-salutation-list';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-salutation-list'), {
        localVue,

        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            }
        },

        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve([
                            {
                                ids: '44e90239c4c546c0896882623f6b3eff',
                                limit: 25,
                                page: 1,
                                totalCountMode: 1
                            }
                        ]);
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            }
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
                `
            },
            'sw-card-view': {
                template: `
                    <div class="sw-card-view">
                        <slot></slot>
                    </div>
                `
            },
            'sw-card': {
                template: `
                    <div class="sw-card">
                        <slot name="grid"></slot>
                    </div>
                `
            },
            'sw-entity-listing': {
                props: ['items', 'allowEdit', 'allowDelete'],
                template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{item}">
                                <slot name="detail-action" v-bind="{ item }" >
                                    <sw-context-menu-item
                                        class="sw-salutation-list__edit-action"
                                        :disabled="!allowEdit"
                                    >
                                        {{ $tc('global.default.edit') }}
                                    </sw-context-menu-item>
                                </slot>
                                <slot name="delete-action" v-bind="{ item }" >
                                    <sw-context-menu-item
                                        class="sw-salutation-list__delete-action"
                                        :disabled="!allowDelete"
                                    >
                                        {{ $tc('global.default.edit') }}
                                    </sw-context-menu-item>
                                </slot>
                            </slot>
                        </template>
                    </div>
                `
            },
            'sw-search-bar': true,
            'sw-icon': true,
            'sw-language-switch': true,
            'sw-button': true,
            'sw-context-menu-item': true
        }
    });
}

describe('module/sw-settings-salutation/page/sw-settings-salutation-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to create a new salutation if have a creator privilege', async () => {
        const wrapper = createWrapper([
            'salutation.creator'
        ]);
        await wrapper.vm.$nextTick();


        await wrapper.vm.$nextTick();
        const createButton = wrapper.find('.sw-settings-salutation-list__create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new salutation if have not a creator privilege', async () => {
        const wrapper = createWrapper([]);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-salutation-list__create');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should not be able to create a new salutation if have privileges which do not contain creator privilege',
        async () => {
            const wrapper = createWrapper([
                'salutation.editor',
                'salutation.deleter'
            ]);
            await wrapper.vm.$nextTick();

            const createButton = wrapper.find('.sw-settings-salutation-list__create');

            expect(createButton.attributes().disabled).toBeTruthy();
        });

    it('should be able to edit a salutation if have a editor privilege', async () => {
        const wrapper = createWrapper([
            'salutation.editor'
        ]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-salutation-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit a salutation if have not a editor privilege', async () => {
        const wrapper = createWrapper([]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-salutation-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should not be able to edit a salutation if have privileges which do not contain editor privilege', async () => {
        const wrapper = createWrapper([
            'salutation.creator',
            'salutation.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-salutation-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit a salutation inline if have a editor privilege', async () => {
        const wrapper = createWrapper([
            'salutation.editor'
        ]);
        await wrapper.vm.$nextTick();
        const entityListing = wrapper.find('.sw-settings-salutation-list-grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to edit a salutation inline if have not a editor privilege', async () => {
        const wrapper = createWrapper([]);
        await wrapper.vm.$nextTick();
        const entityListing = wrapper.find('.sw-settings-salutation-list-grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should not be able to edit a salutation inline if have privileges which do not contain editor privilege',
        async () => {
            const wrapper = createWrapper([
                'salutation.creator',
                'salutation.deleter'
            ]);
            await wrapper.vm.$nextTick();
            const entityListing = wrapper.find('.sw-settings-salutation-list-grid');
            expect(entityListing.exists()).toBeTruthy();
            expect(entityListing.attributes()['allow-inline-edit']).toBeFalsy();
        });

    it('should be able to delete a salutation if have a deleter privilege', async () => {
        const wrapper = createWrapper([
            'salutation.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-salutation-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete a salutation if have not a deleter privilege', async () => {
        const wrapper = createWrapper([]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-salutation-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should not be able to delete a salutation if have privileges which do not contain deleter privilege', async () => {
        const wrapper = createWrapper([
            'salutation.creator',
            'salutation.editor'
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-salutation-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should hide item selection if have not a deleter privilege', async () => {
        const wrapper = createWrapper([]);
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-settings-salutation-list-grid');
        expect(entityList.attributes()['show-selection']).toBeFalsy();
    });

    it('should show item selection if have a deleter privilege', async () => {
        const wrapper = createWrapper(['salutation.deleter']);
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-settings-salutation-list-grid');
        expect(entityList.attributes()['show-selection']).toBeTruthy();
    });
});
