/**
 * @package services-settings
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-bulk-edit-change-type-field-renderer', {
        sync: true,
    }), {
        global: {
            stubs: {
                'sw-bulk-edit-change-type-field-renderer': true,
                'sw-checkbox-field': true,
                'sw-bulk-edit-form-field-renderer': true,
                'sw-bulk-edit-change-type': true,
                'sw-inheritance-switch': true,
                'sw-container': true,
            },
        },
        props: {
            bulkEditData: {
                description: {
                    isChanged: false,
                    type: 'overwrite',
                    value: null,
                },
                manufacturerId: {
                    isChanged: false,
                    type: 'overwrite',
                    value: null,
                },
                active: {
                    isChanged: false,
                    type: 'overwrite',
                    value: false,
                },
                markAsTopseller: {
                    isChanged: false,
                    type: 'overwrite',
                    value: false,
                },
            },
            entity: [],
            formFields: [],
        },
    });
}

describe('src/module/sw-bulk-edit/component/sw-bulk-edit-change-type-field-renderer', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be return data when config value is exists', async () => {
        const formField = {
            name: 'markAsTopseller',
            type: 'bool',
            config: {
                type: 'switch',
                allowOverwrite: true,
            },
        };

        const configValue = wrapper.vm.getConfigValue(formField, 'allowOverwrite');
        expect(configValue).toBe(true);
    });

    it('should be return null when config value is empty', async () => {
        const formField = {
            name: 'markAsTopseller',
            type: 'bool',
            config: {
                type: 'switch',
            },
        };

        const configValue = wrapper.vm.getConfigValue(formField, 'allowOverwrite');
        expect(configValue).toBeNull();
    });

    it('should be return null when config is empty', async () => {
        const formField = {
            name: 'markAsTopseller',
            type: 'bool',
        };

        const configValue = wrapper.vm.getConfigValue(formField, 'allowOverwrite');
        expect(configValue).toBeNull();
    });

    it('should be show the select box', async () => {
        const formField = {
            name: 'markAsTopseller',
            type: 'bool',
            config: {
                type: 'switch',
                allowOverwrite: true,
            },
        };

        const configValue = wrapper.vm.showSelectBoxType(formField);
        expect(configValue).toBeTruthy();
    });

    it('should be not show the select box', async () => {
        const formField = {
            name: 'markAsTopseller',
            type: 'bool',
            config: {
                type: 'switch',
                allowOverwrite: false,
            },
        };

        const configValue = wrapper.vm.showSelectBoxType(formField);
        expect(configValue).toBeFalsy();

        const formField2 = {
            name: 'markAsTopseller',
            type: 'bool',
        };

        const configValue2 = wrapper.vm.showSelectBoxType(formField2);
        expect(configValue2).toBeFalsy();
    });

    it('should be able to restore and remove inheritance', async () => {
        const item = { name: 'description', canInherit: true };

        wrapper.vm.onInheritanceRestore(item);
        expect(wrapper.emitted('inheritance-restore')[0]).toEqual([item]);

        wrapper.vm.onInheritanceRemove(item);
        expect(wrapper.emitted('inheritance-remove')[0]).toEqual([item]);
    });
});
