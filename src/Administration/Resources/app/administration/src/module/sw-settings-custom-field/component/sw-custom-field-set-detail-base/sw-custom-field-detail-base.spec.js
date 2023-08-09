/**
 * @package system-settings
 */
import { shallowMount } from '@vue/test-utils';
import swCustomFieldSetDetailBase from 'src/module/sw-settings-custom-field/component/sw-custom-field-set-detail-base';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

Shopware.Component.register('sw-custom-field-set-detail-base', swCustomFieldSetDetailBase);

const set = {
    id: '9f359a2ab0824784a608fc2a443c5904',
    customFields: {},
    _isNew: false,
};

async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-custom-field-set-detail-base'), {
        propsData: {
            set: set,
        },
        mocks: {
            $tc: key => {
                if (key === 'sw-settings-custom-field.set.detail.labelPosition') {
                    return 'position';
                }
                return 'foo';
            },
        },
        provide: {
            customFieldDataProviderService: {
                getEntityNames() {
                    return 'entity_name_example';
                },
            },
            validationService: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },
        },
        stubs: {
            'sw-card': true,
            'sw-number-field': {
                props: ['label'],
                template: `
                        <input :label="label"
                               class="sw-number-field-stub">
                        </input>
                    `,
            },
            'sw-text-field': true,
            'sw-multi-select': true,
        },
    });
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-set-detail-base/sw-custom-field-detail-base', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have a position field', async () => {
        const wrapper = await createWrapper();

        const positionField = wrapper.findAll('.sw-number-field-stub[label=position]');
        expect(positionField).toHaveLength(1);
    });
});
