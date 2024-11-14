/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/custom-entity',
    status: 200,
    response: {
        data: [],
    },
});

async function createWrapper(privileges = [], isNew = true, currentCustomField = {}) {
    return mount(
        await wrapTestComponent('sw-custom-field-type-entity', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $tc: () => {
                        return 'foo';
                    },
                    $i18n: {
                        fallbackLocale: 'en-GB',
                    },
                },
                provide: {
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                },
                stubs: {
                    'sw-custom-field-type-base': true,
                    'sw-custom-field-translated-labels': true,
                    'sw-single-select': true,
                    'sw-field': true,
                    'sw-switch-field': true,
                    'sw-button': true,
                    'sw-text-field': true,
                    'sw-container': true,
                },
            },
            props: {
                currentCustomField: {
                    id: 'id1',
                    name: 'custom_additional_field_1',
                    config: {
                        label: { 'en-GB': 'Entity Type Field' },
                        customFieldType: 'entity',
                        customFieldPosition: 1,
                        options: [],
                    },
                    _isNew: isNew,
                    ...currentCustomField,
                },
                set: {
                    config: {},
                },
            },
        },
    );
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-type-entity', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should allow entity type selection on new custom field', async () => {
        const wrapper = await createWrapper();
        const entitySelect = wrapper.find('sw-single-select-stub');

        expect(entitySelect.attributes('disabled')).toBeFalsy();
    });

    it('should not allow entity type selection on existing custom field', async () => {
        const wrapper = await createWrapper([], false);
        wrapper.vm.currentCustomField._isNew = false;

        const entitySelect = wrapper.find('sw-single-select-stub');

        expect(entitySelect.attributes('disabled')).toBeTruthy();
    });

    it('should not allow to add options', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-custom-field-type-select__button-add').exists()).toBe(false);
        expect(wrapper.vm.currentCustomField.config.options).toBeUndefined();
    });

    it.each([
        { name: 'new custom field', isNew: true, expected: undefined },
        { name: 'old custom field', isNew: false, expected: 'true' },
    ])('should disable multi select switch: $name', async ({ isNew, expected }) => {
        const wrapper = await createWrapper([], isNew);
        await flushPromises();

        expect(wrapper.find('sw-switch-field-stub').attributes('disabled')).toBe(expected);
    });

    it('should only allow valid component names', async () => {
        const wrapper = await createWrapper([], true, {
            config: {
                componentName: 'foo',
            },
        });
        await flushPromises();

        expect(wrapper.vm.currentCustomField.config.componentName).toBe('sw-entity-single-select');
    });
});
