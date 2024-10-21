/**
 * @package buyers-experience
 */
import { shallowMount } from '@vue/test-utils';

async function createWrapper(customProps = {}) {
    const salesChannel = {};
    salesChannel.getEntityName = () => '';

    return shallowMount(
        await wrapTestComponent('sw-sales-channel-defaults-select', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-entity-multi-select': {
                        template: '<div class="sw-entity-multi-select"></div>',
                        props: ['disabled'],
                    },
                    'sw-entity-single-select': {
                        template: '<div class="sw-entity-single-select"></div>',
                        props: ['disabled'],
                    },
                    'sw-icon': true,
                },
            },
            props: {
                salesChannel,
                propertyName: 'countries',
                propertyLabel: '',
                defaultPropertyName: '',
                defaultPropertyLabel: '',
                ...customProps,
            },
        },
    );
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-defaults-select', () => {
    it('should have selects enabled', async () => {
        const wrapper = await createWrapper();

        const multiSelect = wrapper.getComponent('.sw-sales-channel-detail__select-countries');
        const singleSelect = wrapper.getComponent('.sw-sales-channel-detail__assign-countries');

        expect(multiSelect.props('disabled')).toBe(false);
        expect(singleSelect.props('disabled')).toBe(false);
    });

    it('should have selects disabled', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const multiSelect = wrapper.getComponent('.sw-sales-channel-detail__select-countries');
        const singleSelect = wrapper.getComponent('.sw-sales-channel-detail__assign-countries');

        expect(multiSelect.props('disabled')).toBe(true);
        expect(singleSelect.props('disabled')).toBe(true);
    });
});
