import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-country/component/sw-settings-country-state';
import 'src/app/component/base/sw-card';
import 'src/app/component/base/sw-container';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-country-state'), {
        localVue,

        mocks: {
            $tc: key => key,
            $route: {
                params: {
                    id: 'id'
                }
            },
            $device: {
                getSystemKey: () => {},
                onResize: () => {}
            }
        },

        propsData: {
            country: {
                isNew: () => false,
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
                taxRules: [],
                translated: {},
                translations: [],
                updatedAt: '2020-08-16T06:57:40.559+00:00',
                vatIdRequired: false
            },
            isLoading: false
        },

        provide: {
            repositoryFactory: {
                create: () => ({
                    get: () => {
                        return Promise.resolve({});
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            feature: {
                isActive: () => true
            }
        },

        stubs: {
            'sw-card': Shopware.Component.build('sw-card'),
            'sw-container': Shopware.Component.build('sw-container'),
            'sw-button': true,
            'sw-icon': true,
            'sw-simple-search-field': true,
            'sw-context-menu-item': true,
            'sw-one-to-many-grid': {
                props: ['columns', 'allowDelete'],
                template: `
                    <div>
                        <template v-for="item in columns">
                            <slot name="more-actions" v-bind="{ item }"></slot>
                            <slot name="delete-action" :item="item">
                                <sw-context-menu-item
                                    class="sw-one-to-many-grid__delete-action"
                                    variant="danger"
                                    :disabled="!allowDelete"
                                    @click="deleteItem(item.id)">
                                    {{ $tc('global.default.delete') }}
                                </sw-context-menu-item>
                            </slot>
                        </template>
                    </div>
                `
            }
        }
    });
}

describe('module/sw-settings-country/component/sw-settings-country-state', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to create a new country state', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-country-state__add-country-state-button');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new country state', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-country-state__add-country-state-button');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit a country state', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-settings-country-state__edit-country-state-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit a country state', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-settings-country-state__edit-country-state-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete a country state', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-one-to-many-grid__delete-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete a country state', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-one-to-many-grid__delete-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });
});
