import 'src/app/component/filter/sw-range-filter';
import 'src/app/component/filter/sw-base-filter';
import { createLocalVue, shallowMount, enableAutoDestroy } from '@vue/test-utils';

const { Criteria } = Shopware.Data;

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-range-filter'), {
        localVue,
        stubs: {
            'sw-base-filter': Shopware.Component.build('sw-base-filter'),
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-icon': true,
            'sw-field-error': true
        },
        propsData: {
            isShowDivider: true,
            value: {
                from: null,
                to: null
            },
            active: true,
            property: 'releaseDate',
            showResetButton: false,
            title: 'Release Date'
        },
        mocks: {
            $tc: key => key
        },
        provide: {}
    });
}

enableAutoDestroy(afterEach);

describe('src/app/component/filter/sw-range-filter', () => {
    it('should emit `filter-update` event when `From` value exits', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            value: {
                from: '2021-01-20',
                to: null
            }
        });

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            [Criteria.range('releaseDate', { gte: '2021-01-20' })]
        ]);
    });

    it('should emit `filter-update` event when `To` value exits', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            value: {
                from: null,
                to: '2021-01-23'
            }
        });

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            [Criteria.range('releaseDate', { lte: '2021-01-23' })]
        ]);
    });

    it('should emit `filter-update` event when `From` and `To` value exits', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            value: {
                from: '2021-01-20',
                to: '2021-01-23'
            }
        });

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            [Criteria.range('releaseDate', { gte: '2021-01-20', lte: '2021-01-23' })]
        ]);
    });

    it('should render From field and To field on the same line', () => {
        const wrapper = createWrapper();

        const container = wrapper.find('.sw-container');
        const divider = wrapper.find('.sw-range-filter__divider');

        expect(divider.exists()).toBeTruthy();
        expect(container.attributes('columns')).toBe('1fr 12px 1fr');
    });

    it('should render From field and To field in different line', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            isShowDivider: false
        });

        const container = wrapper.find('.sw-container');
        const divider = wrapper.find('.sw-range-filter__divider');

        expect(divider.exists()).toBeFalsy();
        expect(container.attributes('columns')).toBe('1fr');
    });
});
