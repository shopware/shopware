import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-custom-field/component/sw-custom-field-type-entity';
import 'src/module/sw-settings-custom-field/component/sw-custom-field-type-select';
import 'src/module/sw-settings-custom-field/component/sw-custom-field-type-base';

function createWrapper(privileges = [], isNew = true) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-custom-field-type-entity'), {
        localVue,
        mocks: {
            $tc: () => {
                return 'foo';
            },
            $i18n: {
                fallbackLocale: 'en-GB'
            }
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            }
        },
        propsData: {
            currentCustomField: {
                id: 'id1',
                name: 'custom_additional_field_1',
                config: {
                    label: { 'en-GB': 'Entity Type Field' },
                    customFieldType: 'entity',
                    customFieldPosition: 1
                },
                _isNew: isNew
            },
            set: {
                config: {}
            }
        },
        stubs: {
            'sw-custom-field-type-base': true,
            'sw-custom-field-translated-labels': true,
            'sw-single-select': true,
            'sw-field': true
        }
    });
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-type-entity', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should allow entity type selection on new custom field', () => {
        const wrapper = createWrapper();
        const entitySelect = wrapper.find('sw-single-select-stub');

        expect(entitySelect.attributes('disabled')).toBeFalsy();
    });

    it('should not allow entity type selection on existing custom field', () => {
        const wrapper = createWrapper([], false);
        wrapper.vm.currentCustomField._isNew = false;

        const entitySelect = wrapper.find('sw-single-select-stub');

        expect(entitySelect.attributes('disabled')).toBeTruthy();
    });
});
