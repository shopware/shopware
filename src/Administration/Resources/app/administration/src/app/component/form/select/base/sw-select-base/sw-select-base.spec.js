/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

import 'src/app/component/form/select/base/sw-single-select';

const createWrapper = async (props = {}, attrs = {}, options = {}) => {
    const wrapper = mount(await wrapTestComponent('sw-select-base', { sync: true }), {
        props,
        attrs,
        ...options,
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

    it('should remove disabled selects from the tab order', async () => {
        const wrapper = await createWrapper({ disabled: true });

        expect(wrapper.find('.sw-select__selection').attributes('tabindex')).toBe('-1');
    });

    it('should not emit a collapsed event when not expanded', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.expanded = false;

        wrapper.vm.collapse();

        expect(wrapper.emitted('select-collapsed')).toBeUndefined();
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

    describe('keyboard', () => {
        let wrapper;

        async function createFocusedSelection() {
            const container = document.createElement('div');
            document.body.appendChild(container);
            wrapper = await createWrapper(
                {},
                {},
                {
                    attachTo: container,
                    slots: { 'sw-select-selection': '<input class="search-input" tabindex="-1">' },
                },
            );

            const selection = wrapper.find('.sw-select__selection');
            selection.element.focus();
            await flushPromises();

            return selection;
        }

        function dispatchKeydown(element, init) {
            const event = new KeyboardEvent('keydown', { bubbles: true, cancelable: true, ...init });
            element.dispatchEvent(event);

            return event;
        }

        afterEach(() => {
            wrapper.unmount();
            document.body.innerHTML = '';
        });

        it('should show the focus without expanding when the selection gets focus', async () => {
            const selection = await createFocusedSelection();

            expect(selection.attributes('aria-expanded')).toBe('false');
            expect(wrapper.find('.sw-select').classes()).toContain('has--focus');
        });

        it.each([
            'ArrowDown',
            'ArrowUp',
            'Enter',
            ' ',
        ])('should expand on "%s" without passing the key on to the result list', async (key) => {
            const selection = await createFocusedSelection();
            const onDocumentKeydown = jest.fn();
            document.addEventListener('keydown', onDocumentKeydown);

            try {
                dispatchKeydown(selection.element, { key });
                await flushPromises();
            } finally {
                document.removeEventListener('keydown', onDocumentKeydown);
            }

            expect(selection.attributes('aria-expanded')).toBe('true');
            expect(onDocumentKeydown).not.toHaveBeenCalled();
        });

        it('should expand on a printable character and let it reach the search', async () => {
            const selection = await createFocusedSelection();

            const event = dispatchKeydown(selection.element, { key: 'a' });
            await flushPromises();

            expect(selection.attributes('aria-expanded')).toBe('true');
            expect(event.defaultPrevented).toBe(false);
        });

        it('should keep the focus on the selection when ESC collapses the select', async () => {
            const selection = await createFocusedSelection();
            dispatchKeydown(selection.element, { key: 'ArrowDown' });
            const searchInput = wrapper.find('.search-input').element;
            searchInput.focus();

            dispatchKeydown(searchInput, { key: 'Escape' });
            await flushPromises();

            expect(selection.attributes('aria-expanded')).toBe('false');
            expect(document.activeElement).toBe(selection.element);
        });

        it('should leave SHIFT+TAB to the browser, starting from the selection', async () => {
            const selection = await createFocusedSelection();
            dispatchKeydown(selection.element, { key: 'ArrowDown' });
            const searchInput = wrapper.find('.search-input').element;
            searchInput.focus();

            const event = dispatchKeydown(searchInput, { key: 'Tab', shiftKey: true });
            await flushPromises();

            expect(selection.attributes('aria-expanded')).toBe('false');
            expect(event.defaultPrevented).toBe(false);
            expect(document.activeElement).toBe(selection.element);
        });

        it('should not take the focus back when the select collapses after the focus left it', async () => {
            const selection = await createFocusedSelection();
            dispatchKeydown(selection.element, { key: 'ArrowDown' });
            const outside = document.createElement('button');
            document.body.appendChild(outside);

            outside.focus();
            outside.click();
            await flushPromises();

            expect(selection.attributes('aria-expanded')).toBe('false');
            expect(document.activeElement).toBe(outside);
        });
    });
});
