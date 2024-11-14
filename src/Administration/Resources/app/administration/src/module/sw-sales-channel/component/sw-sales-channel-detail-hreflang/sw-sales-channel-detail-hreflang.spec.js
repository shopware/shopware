/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';

async function createWrapper(customProps = {}) {
    return mount(
        await wrapTestComponent('sw-sales-channel-detail-hreflang', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-card': {
                        template: '<div class="sw-card"><slot></slot></div>',
                    },
                    'sw-switch-field': true,
                    'sw-entity-single-select': true,
                },
            },
            props: {
                salesChannel: {
                    hreflangActive: true,
                },
                ...customProps,
            },
        },
    );
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-detail-hreflang', () => {
    it('should enable the sw-switch-field and the sw-entity-single-select', async () => {
        const wrapper = await createWrapper();

        const switchField = wrapper.find('sw-switch-field-stub');
        expect(switchField.attributes().disabled).toBeUndefined();

        const entitySingleSelect = wrapper.find('sw-entity-single-select-stub');
        expect(entitySingleSelect.attributes().disabled).toBeUndefined();
    });

    it('should disable the sw-switch-field and the sw-entity-single-select', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const switchField = wrapper.find('sw-switch-field-stub');
        expect(switchField.attributes().disabled).toBe('true');

        const entitySingleSelect = wrapper.find('sw-entity-single-select-stub');
        expect(entitySingleSelect.attributes().disabled).toBe('true');
    });
});
