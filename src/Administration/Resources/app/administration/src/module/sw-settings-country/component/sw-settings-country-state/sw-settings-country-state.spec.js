/**
 * @package system-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-settings-country-state', {
        sync: true,
    }), {
        props: {
            country: {
                isNew: () => true,
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
                vatIdRequired: false,
            },
            isLoading: false,
        },

        global: {
            mocks: {
                $tc: key => key,
                $route: {
                    params: {
                        id: 'id',
                    },
                },
                $device: {
                    getSystemKey: () => {},
                    onResize: () => {},
                },
            },

            provide: {
                repositoryFactory: {
                    create: () => ({
                        get: () => {
                            return Promise.resolve({});
                        },
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) { return true; }

                        return privileges.includes(identifier);
                    },
                },
            },

            stubs: {
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-ignore-class': true,
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-button': true,
                'sw-icon': true,
                'sw-simple-search-field': true,
                'sw-context-menu-item': true,
                'sw-extension-component-section': true,
                'sw-one-to-many-grid': {
                    props: ['allowDelete', 'collection'],
                    template: `
                    <div class="sw-one-to-many-grid">
                    <template v-for="item in collection">
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
                `,
                },
                'sw-empty-state': true,
            },
        },

    });
}

describe('module/sw-settings-country/component/sw-settings-country-state', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show empty state', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.find('sw-empty-state-stub').exists()).toBeTruthy();
    });

    it('should be able to create a new country state', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-country-state__add-country-state-button');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new country state', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-country-state__add-country-state-button');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit a country state', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);

        await wrapper.setProps({
            country: {
                ...wrapper.vm.country,
                states: [
                    {
                        id: '1234',
                        shortCode: 'DE-BE',
                        translated: {
                            name: 'Berlin',
                        },
                    },
                ],
            },
        });

        expect(wrapper.find('sw-empty-state-stub').exists()).toBeFalsy();

        const editMenuItem = wrapper.find('.sw-settings-country-state__edit-country-state-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit a country state', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            country: {
                ...wrapper.vm.country,
                states: [
                    {
                        id: '1234',
                        shortCode: 'DE-BE',
                        translated: {
                            name: 'Berlin',
                        },
                    },
                ],
            },
        });

        expect(wrapper.find('sw-empty-state-stub').exists()).toBeFalsy();
        const editMenuItem = wrapper.find('.sw-settings-country-state__edit-country-state-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete a country state', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            country: {
                ...wrapper.vm.country,
                states: [
                    {
                        id: '1234',
                        shortCode: 'DE-BE',
                        translated: {
                            name: 'Berlin',
                        },
                    },
                ],
            },
        });

        expect(wrapper.find('sw-empty-state-stub').exists()).toBeFalsy();
        const editMenuItem = wrapper.find('.sw-one-to-many-grid__delete-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete a country state', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            country: {
                ...wrapper.vm.country,
                states: [
                    {
                        id: '1234',
                        shortCode: 'DE-BE',
                        translated: {
                            name: 'Berlin',
                        },
                    },
                ],
            },
        });

        expect(wrapper.find('sw-empty-state-stub').exists()).toBeFalsy();
        const editMenuItem = wrapper.find('.sw-one-to-many-grid__delete-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });
});
