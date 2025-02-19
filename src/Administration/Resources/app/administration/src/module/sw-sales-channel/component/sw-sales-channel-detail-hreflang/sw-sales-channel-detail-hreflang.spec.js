/**
 * @sw-package discovery
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
                    'mt-card': {
                        template: '<div class="mt-card"><slot></slot></div>',
                    },

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

        const switchField = wrapper.findComponent('.mt-switch');
        expect(switchField.props().disabled).toBeUndefined();

        const entitySingleSelect = wrapper.find('sw-entity-single-select-stub');
        expect(entitySingleSelect.attributes().disabled).toBeUndefined();
    });

    it('should disable the sw-switch-field and the sw-entity-single-select', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const switchField = wrapper.findComponent('.mt-switch');
        expect(switchField.props().disabled).toBe(true);

        const entitySingleSelect = wrapper.find('sw-entity-single-select-stub');
        expect(entitySingleSelect.attributes().disabled).toBe('true');
    });
});
