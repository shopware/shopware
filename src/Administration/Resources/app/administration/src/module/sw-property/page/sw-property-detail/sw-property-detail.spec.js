/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-property-detail', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return {
                                id: '1a2b3c',
                                name: 'Test property',
                                entity: 'property',
                            };
                        },
                        get: () =>
                            Promise.resolve({
                                id: '1a2b3c',
                                name: 'Test property',
                                entity: 'property',
                                options: {
                                    entity: 'property_options_group',
                                },
                            }),
                        search: () => Promise.resolve({}),
                    }),
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
            },
            stubs: {
                'sw-search-bar': true,
                'sw-page': {
                    template: `
                        <div class="sw-page"><slot name="search-bar"></slot>
                            <slot name="smart-bar-actions"></slot>
                        </div>`,
                },
                'sw-button-process': true,
                'sw-language-switch': true,
                'sw-card-view': true,
                'sw-container': true,
                'sw-field': true,
                'sw-language-info': true,
                'sw-skeleton': true,
                'sw-property-detail-base': true,
                'sw-property-option-list': true,
                'sw-custom-field-set-renderer': true,
            },
        },
    });
}

describe('module/sw-property/page/sw-property-detail', () => {
    it('should not be able to save the property', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.find('.sw-property-detail__save-action');

        expect(saveButton.attributes()['is-loading']).toBeFalsy();
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save the property', async () => {
        global.activeAclRoles = ['property.editor'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false,
        });
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-property-detail__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should render the search bar only when the admin search is enabled', async () => {
        Shopware.Context.app.adminEsEnable = true;
        const enabledWrapper = await createWrapper();
        await flushPromises();

        const searchBar = enabledWrapper.find('sw-search-bar-stub');
        expect(searchBar.exists()).toBe(true);
        expect(searchBar.attributes('initial-search-type')).toBe('property_group');
        enabledWrapper.unmount();

        Shopware.Context.app.adminEsEnable = false;
        const disabledWrapper = await createWrapper();
        await flushPromises();

        expect(disabledWrapper.find('sw-search-bar-stub').exists()).toBe(false);
        disabledWrapper.unmount();
    });
});
