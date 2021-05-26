import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-property/page/sw-property-detail';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-property-detail'), {
        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => {
                        return {
                            id: '1a2b3c',
                            name: 'Test property',
                            entity: 'property'
                        };
                    },
                    get: () => Promise.resolve({
                        id: '1a2b3c',
                        name: 'Test property',
                        entity: 'property',
                        options: {
                            entity: 'property_options_group'
                        }
                    }),
                    search: () => Promise.resolve({})
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([])
            }
        },
        stubs: {
            'sw-page': {
                template: `
<div class="sw-page">
    <slot name="smart-bar-actions"></slot>
</div>`
            },
            'sw-button': true,
            'sw-button-process': true,
            'sw-language-switch': true,
            'sw-card-view': true,
            'sw-card': true,
            'sw-container': true,
            'sw-field': true,
            'sw-language-info': true
        }
    });
}

describe('module/sw-property/page/sw-property-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to save the property', async () => {
        const wrapper = createWrapper();
        await wrapper.setData({
            isLoading: false
        });

        const saveButton = wrapper.find('.sw-property-detail__save-action');

        expect(saveButton.attributes()['is-loading']).toBeFalsy();
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save the property', async () => {
        const wrapper = await createWrapper([
            'property.editor'
        ]);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false
        });
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-property-detail__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });
});
