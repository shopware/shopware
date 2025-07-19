/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import 'src/app/component/rule/condition-type/sw-condition-date-range';
import 'src/app/component/rule/sw-condition-base';

async function createWrapper(useTime) {
    return mount(await wrapTestComponent('sw-condition-date-range', { sync: true }), {
        propsData: {
            condition: {
                value: {
                    useTime: useTime,
                },

                getEntityName: () => 'rule_condition',
            },
        },
        global: {
            stubs: {
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-loader': true,
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-highlight-text': true,
                'sw-condition-base': true,
                'sw-condition-type-select': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-field-error': true,
                'sw-datepicker': true,
            },
            provide: {
                conditionDataProviderService: new ConditionDataProviderService(),
                availableTypes: {},
                availableGroups: [],
                childAssociationField: {},
                validationService: {},
                insertNodeIntoTree: () => ({}),
                removeNodeFromTree: () => ({}),
                createCondition: () => ({}),
                conditionScopes: [],
                unwrapAllLineItemsCondition: () => ({}),
            },
        },
    });
}

describe('component/rule/sw-condition-date-range', () => {
    it('should toggle selection without and with time', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const datepickerCollection = wrapper.findAll('.wrapper .dp__main');

        expect(datepickerCollection).toHaveLength(2);

        datepickerCollection.forEach((datepicker) => {
            expect(datepicker.attributes('type')).toBe('date');
        });

        await wrapper.find('.sw-single-select input').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--true').trigger('click');
        await flushPromises();

        datepickerCollection.forEach((datepicker) => {
            expect(datepicker.attributes('type')).toBe('datetime');
        });
    });

    it('should select a fromDate without time', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const fromDateInput = wrapper.find('.wrapper[name="sw-field--fromDate"] input');

        expect(fromDateInput.attributes('value')).toBe('');
        await fromDateInput.trigger('click');
        await flushPromises();

        document.querySelector('[data-test-id="year-toggle-overlay-0"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="1900"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="month-toggle-overlay-0"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="Jan"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[id="1900-01-01"]').dispatchEvent(new Event('click'));
        await flushPromises();

        expect(fromDateInput.attributes('value')).toBe('1900/01/01');
        expect(wrapper.vm.fromDate).toBe('1900-01-01T00:00:00+00:00');
    });

    it('should select a fromDate with time', async () => {
        const wrapper = await createWrapper(true);
        await flushPromises();

        const fromDateInput = wrapper.find('.wrapper[name="sw-field--fromDate"] input');

        expect(fromDateInput.attributes('value')).toBe('');
        await fromDateInput.trigger('click');
        await flushPromises();

        document.querySelector('[data-test-id="year-toggle-overlay-0"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="1900"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="month-toggle-overlay-0"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="Jan"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="hours-toggle-overlay-btn-0"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="12"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="minutes-toggle-overlay-btn-0"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="30"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[id="1900-01-01"]').dispatchEvent(new Event('click'));
        await flushPromises();

        expect(fromDateInput.attributes('value')).toBe('1900/01/01, 12:30');
        expect(wrapper.vm.fromDate).toBe('1900-01-01T12:30:00+00:00');
    });

    it('should select a toDate without time', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const fromDateInput = wrapper.find('.wrapper[name="sw-field--toDate"] input');

        expect(fromDateInput.attributes('value')).toBe('');
        await fromDateInput.trigger('click');
        await flushPromises();

        document.querySelector('[data-test-id="year-toggle-overlay-0"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="1900"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="month-toggle-overlay-0"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="Jan"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[id="1900-01-01"]').dispatchEvent(new Event('click'));
        await flushPromises();

        expect(fromDateInput.attributes('value')).toBe('1900/01/01');
        expect(wrapper.vm.toDate).toBe('1900-01-01T23:59:59+00:00');
    });

    it('should select a toDate with time', async () => {
        const wrapper = await createWrapper(true);
        await flushPromises();

        const fromDateInput = wrapper.find('.wrapper[name="sw-field--toDate"] input');

        expect(fromDateInput.attributes('value')).toBe('');
        await fromDateInput.trigger('click');
        await flushPromises();

        document.querySelector('[data-test-id="year-toggle-overlay-0"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="1900"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="month-toggle-overlay-0"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="Jan"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="hours-toggle-overlay-btn-0"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="12"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="minutes-toggle-overlay-btn-0"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[data-test-id="30"]').dispatchEvent(new Event('click'));
        await flushPromises();

        document.querySelector('[id="1900-01-01"]').dispatchEvent(new Event('click'));
        await flushPromises();

        expect(fromDateInput.attributes('value')).toBe('1900/01/01, 12:30');
        expect(wrapper.vm.toDate).toBe('1900-01-01T12:30:00+00:00');
    });
});
