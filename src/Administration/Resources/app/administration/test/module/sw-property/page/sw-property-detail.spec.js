import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-property/page/sw-property-detail';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-property-detail'), {
        localVue,
        mocks: {
            $tc: () => {},
            $device: {
                getSystemKey: () => {}
            }
        },
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
                        entity: 'property'
                    }),
                    search: () => Promise.resolve({})
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
            'sw-page': true,
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
    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should not be able to save the property', () => {
        const wrapper = createWrapper();
        wrapper.setData({
            isLoading: false
        });

        const saveButton = wrapper.find('.sw-property-detail__save-action');

        expect(saveButton.attributes().isLoading).toBeFalsy();
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save the property', () => {
        const wrapper = createWrapper([
            'property.editor'
        ]);
        wrapper.setData({
            isLoading: false
        });

        const saveButton = wrapper.find('.sw-property-detail__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });
});
