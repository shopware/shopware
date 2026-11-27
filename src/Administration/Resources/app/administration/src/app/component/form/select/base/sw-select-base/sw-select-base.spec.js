/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

import 'src/app/component/form/select/base/sw-single-select';

const createWrapper = async (props = {}, attrs = {}, attachTo = undefined) => {
    const wrapper = mount(await wrapTestComponent('sw-select-base', { sync: true }), {
        props,
        attrs,
        attachTo,
        global: {
            stubs: {
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-help-text': true,
                'sw-ai-copilot-badge': true,
                'sw-inheritance-switch': true,
                'sw-loader': true,
            },
        },
    });

    await flushPromises();

    return wrapper;
};

describe('components/sw-select-base', () => {
    it('should show the clearable icon by default when required is not set', async () => {
        const wrapper = await createWrapper();

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(true);
    });

    it('should not show the clearable icon by default when required is true', async () => {
        const wrapper = await createWrapper({}, { required: true });

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(false);
    });

    it('should show the clearable icon when required is false', async () => {
        const wrapper = await createWrapper({}, { required: false });

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(true);
    });

    it('should show the clearable icon when explicitly set to true even if required', async () => {
        const wrapper = await createWrapper({ showClearableButton: true }, { required: true });

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(true);
    });

    it('should not show the clearable icon when explicitly set to false even if not required', async () => {
        const wrapper = await createWrapper({ showClearableButton: false }, { required: false });

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(false);
    });

    /**
     * Tabbing out of a select cleared its value.
     * @regression
     * @see https://github.com/shopware/shopware/issues/18793
     */
    describe('tabbing out of a select', () => {
        /** @regression */
        it('should remove disabled selects from the tab order', async () => {
            const wrapper = await createWrapper({ disabled: true });

            expect(wrapper.find('.sw-select__selection').attributes('tabindex')).toBe('-1');
        });

        /** @regression */
        it('should not emit a collapsed event when not expanded', async () => {
            const wrapper = await createWrapper();
            wrapper.vm.expanded = false;

            wrapper.vm.collapse();

            expect(wrapper.emitted('select-collapsed')).toBeUndefined();
        });
    });

    it('should trigger clear event when user clicks on clearable icon', async () => {
        const wrapper = await createWrapper({ showClearableButton: true });

        const clearableIcon = wrapper.find('.sw-select__select-indicator-clear');

        // expect no clear event
        expect(wrapper.emitted('clear')).toBeUndefined();

        // click on clear
        await clearableIcon.trigger('click');

        // expect clear event thrown
        expect(wrapper.emitted('clear')).toHaveLength(1);
    });

    /**
     * Clicks near the select border were treated as outside clicks and closed the dropdown.
     * @regression
     * @see https://github.com/shopware/shopware/issues/16542
     */
    it('should not collapse when the event target is outside but the click position is inside the select', async () => {
        const wrapper = await createWrapper();
        const selection = wrapper.find('.sw-select__selection').element;
        const originalElementsFromPoint = document.elementsFromPoint;

        try {
            document.elementsFromPoint = jest.fn(() => [selection, document.body]);
            wrapper.vm.collapse = jest.fn();

            wrapper.vm.listenToClickOutside({
                target: document.body,
                clientX: 10,
                clientY: 10,
            });

            expect(wrapper.vm.collapse).not.toHaveBeenCalled();
        } finally {
            document.elementsFromPoint = originalElementsFromPoint;
        }
    });

    it('should collapse when the event target and click position are outside the select', async () => {
        const wrapper = await createWrapper();
        const originalElementsFromPoint = document.elementsFromPoint;

        try {
            document.elementsFromPoint = jest.fn(() => [document.body]);
            wrapper.vm.collapse = jest.fn();

            wrapper.vm.listenToClickOutside({
                target: document.body,
                clientX: 10,
                clientY: 10,
            });

            expect(wrapper.vm.collapse).toHaveBeenCalledTimes(1);
        } finally {
            document.elementsFromPoint = originalElementsFromPoint;
        }
    });

    describe('SHIFT+TAB out of an expanded select', () => {
        let wrappers = [];

        function appendContainer() {
            const container = document.createElement('div');
            document.body.appendChild(container);

            return container;
        }

        function appendElement(html) {
            appendContainer().innerHTML = html;

            return document.body.lastElementChild.firstElementChild;
        }

        async function createAttachedSelect() {
            const wrapper = await createWrapper({}, {}, appendContainer());
            wrappers.push(wrapper);

            return wrapper;
        }

        async function pressShiftTab(wrapper) {
            const selection = wrapper.find('.sw-select__selection');
            await selection.trigger('click');
            await selection.trigger('keydown', { key: 'Tab', shiftKey: true });
            await flushPromises();
        }

        beforeEach(() => {
            // jsdom renders no layout, so every element would count as hidden
            jest.spyOn(Element.prototype, 'getClientRects').mockReturnValue([{}]);
        });

        afterEach(() => {
            wrappers.forEach((wrapper) => wrapper.unmount());
            wrappers = [];
            document.body.innerHTML = '';
            jest.restoreAllMocks();
        });

        it('should focus the previous link without clicking it', async () => {
            const link = appendElement('<a href="#/sw/product/index">Products</a>');
            const onClick = jest.fn((event) => event.preventDefault());
            link.onclick = onClick;
            const wrapper = await createAttachedSelect();

            await pressShiftTab(wrapper);

            expect(document.activeElement).toBe(link);
            expect(onClick).not.toHaveBeenCalled();
        });

        it('should skip previous elements that are hidden, disabled or out of the tab order', async () => {
            const input = appendElement('<input type="text">');
            const hiddenLink = appendElement('<a href="#/hidden">Hidden</a>');
            hiddenLink.getClientRects = () => [];
            appendElement('<button type="button" disabled>Disabled</button>');
            appendElement('<button type="button" tabindex="-1">Decrease</button>');
            const wrapper = await createAttachedSelect();

            await pressShiftTab(wrapper);

            expect(document.activeElement).toBe(input);
        });

        it('should treat an element with disabled="false" as focusable', async () => {
            const link = appendElement('<a href="#/sw/product/index" disabled="false">Go back</a>');
            const wrapper = await createAttachedSelect();

            await pressShiftTab(wrapper);

            expect(document.activeElement).toBe(link);
        });

        it('should expand a previous select by focusing its selection', async () => {
            const previousSelect = await createAttachedSelect();
            const wrapper = await createAttachedSelect();

            await pressShiftTab(wrapper);

            expect(wrapper.find('.sw-select__selection').attributes('aria-expanded')).toBe('false');
            expect(previousSelect.find('.sw-select__selection').attributes('aria-expanded')).toBe('true');
        });
    });
});
