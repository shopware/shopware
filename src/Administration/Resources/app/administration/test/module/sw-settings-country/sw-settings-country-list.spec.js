import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-country/page/sw-settings-country-list';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-country-list'), {
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
                                active: true,
                                apiAlias: null,
                                createdAt: '2020-08-12T02:49:39.974+00:00',
                                customFields: null,
                                customerAddresses: [],
                                displayStateInRegistration: false,
                                forceStateInRegistration: false,
                                id: '44de136acf314e7184401d36406c1e90',
                                iso: 'AL',
                                iso3: 'ALB',
                                name: 'Albania',
                                orderAddresses: [],
                                position: 10,
                                salesChannelDefaultAssignments: [],
                                salesChannels: [],
                                shippingAvailable: true,
                                states: [],
                                taxFree: false,
                                taxRules: [],
                                translated: {},
                                translations: [],
                                updatedAt: '2020-08-16T06:57:40.559+00:00'
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
            },
            feature: {
                isActive: () => true
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
                props: ['items', 'detailPageLinkText'],
                template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }">
                                <sw-context-menu-item
                                    class="sw-country-list__edit-action">
                                    {{ detailPageLinkText }}
                                </sw-context-menu-item>
                            </slot>
                        </template>
                    </div>
                `
            },
            'sw-language-switch': true,
            'sw-search-bar': true,
            'sw-context-menu-item': true,
            'sw-icon': true,
            'sw-button': true
        }
    });
}

describe('module/sw-settings-country/page/sw-settings-country-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to view a country', async () => {
        const wrapper = createWrapper([
            'country.viewer'
        ]);
        await wrapper.vm.$nextTick();

        const elementItemAction = wrapper.find('.sw-country-list__edit-action');

        expect(elementItemAction.attributes().disabled).toBeFalsy();
        expect(elementItemAction.text()).toBe('global.default.view');
    });

    it('should be able to create a new country', async () => {
        const wrapper = createWrapper([
            'country.creator'
        ]);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-country-list__button-create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new country', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-country-list__button-create');

        expect(createButton.attributes().disabled).toBeTruthy();
    });


    it('should be able to edit a country', async () => {
        const wrapper = await createWrapper([
            'country.editor'
        ]);
        await wrapper.vm.$nextTick();

        const elementItemAction = wrapper.find('.sw-country-list__edit-action');

        expect(elementItemAction.attributes().disabled).toBeFalsy();
        expect(elementItemAction.text()).toBe('global.default.edit');
    });

    it('should not be able to edit a country', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-country-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to inline edit a country', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-settings-country-list-grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to inline edit a country', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-settings-country-list-grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should be able to delete a country', async () => {
        const wrapper = createWrapper([
            'country.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-country-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete a country', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-country-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete mutilple country', async () => {
        const wrapper = createWrapper([
            'country.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const deleteSelection = wrapper.find('.sw-settings-country-list-grid');
        expect(deleteSelection.attributes()['show-selection']).toBeTruthy();
    });
});
