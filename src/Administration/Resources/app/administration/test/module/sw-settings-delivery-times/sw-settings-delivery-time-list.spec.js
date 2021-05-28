import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-delivery-times/page/sw-settings-delivery-time-list';
import 'src/app/component/base/sw-card';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-delivery-time-list'), {
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
                                id: '123abc',
                                name: '1 - 3 weeks',
                                min: 1,
                                max: 3,
                                unit: 'week'
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
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot></slot>
                    </div>`
            },
            'sw-button': true,
            'sw-icon': true,
            'sw-search-bar': true,
            'sw-language-switch': true,
            'sw-context-menu-item': true,
            'sw-card-view': true,
            'sw-card': Shopware.Component.build('sw-card'),
            'sw-entity-listing': {
                props: ['items', 'allowEdit', 'allowDelete', 'detailRoute'],
                template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }">
                                <slot name="detail-action" v-bind="{ item }">
                                    <sw-context-menu-item-stub class="sw-entity-listing__context-menu-edit-action"
                                                          v-if="detailRoute"
                                                          :disabled="!allowEdit"
                                                          :routerLink="{ name: detailRoute, params: { id: item.id } }">
                                    </sw-context-menu-item-stub>
                                </slot>

                                <slot name="delete-action" v-bind="{ item }">
                                    <sw-context-menu-item-stub :disabled="!allowDelete"
                                                          class="sw-entity-listing__context-menu-edit-delete">
                                    </sw-context-menu-item-stub>
                                </slot>
                            </slot>
                        </template>
                    </div>`
            }
        }
    });
}

describe('module/sw-settings-delivery-times/page/sw-settings-delivery-time-list', () => {
    it('should not be able to create a new delivery time if user does not have create permission', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-delivery-time-list__create');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to create a new delivery time if user has create permission', async () => {
        const wrapper = createWrapper(['delivery_times.creator']);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-delivery-time-list__create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit if user does not have edit permission', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit if user has edit permission', async () => {
        const wrapper = createWrapper(['delivery_times.editor']);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete if user does not have delete permission', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-delete');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete if user has delete permission', async () => {
        const wrapper = createWrapper(['delivery_times.deleter']);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-delete');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should hide item selection if user does not have delete permission', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-settings-delivery-time-list-grid');
        expect(entityList.attributes()['show-selection']).toBeFalsy();
    });

    it('should show item selection if user has delete permission', async () => {
        const wrapper = createWrapper(['delivery_times.deleter']);
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-settings-delivery-time-list-grid');
        expect(entityList.attributes()['show-selection']).toBeTruthy();
    });
});
