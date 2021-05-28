import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-units/page/sw-settings-units';


function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-units'), {
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
                                id: '1a2b3c',
                                name: 'Gramm',
                                shortCode: 'g'
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
            'sw-data-grid': {
                props: ['dataSource'],
                template: `
                    <div>
                        <template v-for="item in dataSource">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>`
            },
            'sw-search-bar': true,
            'sw-icon': true,
            'sw-language-switch': true,
            'sw-button': true,
            'sw-card': {
                template: '<div><slot></slot><slot name="grid"></slot></div>'
            },
            'sw-card-view': {
                template: `
                        <div class="sw-card-view">
                            <slot></slot>
                        </div>
                    `
            },
            'sw-empty-state': true,
            'sw-context-menu-item': true
        }
    });
}

describe('module/sw-settings-units/page/sw-settings-units', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to create a new units', async () => {
        const wrapper = createWrapper([
            'scale_unit.creator'
        ]);
        await wrapper.vm.$nextTick();

        const addButton = wrapper.find('.sw-settings-units__create-action');

        expect(addButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new units', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const addButton = wrapper.find('.sw-settings-units__create-action');

        expect(addButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit a unit', async () => {
        const wrapper = createWrapper([
            'scale_unit.editor'
        ]);
        await wrapper.vm.$nextTick();

        const dataGrid = wrapper.find('.sw-settings-units-grid');

        expect(dataGrid.exists()).toBeTruthy();
        expect(dataGrid.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to edit a unit', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const dataGrid = wrapper.find('.sw-settings-units-grid');

        expect(dataGrid.exists()).toBeTruthy();
        expect(dataGrid.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should be able to delete a units', async () => {
        const wrapper = createWrapper([
            'scale_unit.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-settings-units__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete a units', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-settings-units__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });
});
