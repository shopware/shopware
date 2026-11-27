/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

import 'src/app/component/form/select/base/sw-single-select';

const createWrapper = async (props = {}, attrs = {}, { mocks = {}, ...options } = {}) => {
    const wrapper = mount(await wrapTestComponent('sw-select-base', { sync: true }), {
        props,
        attrs,
        ...options,
        global: {
            mocks,
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

        async function createFocusedSelection(mocks = {}, props = {}) {
            const container = document.createElement('div');
            document.body.appendChild(container);
            wrapper = await createWrapper(
                props,
                {},
                {
                    mocks,
                    attachTo: container,
                    slots: {
                        'sw-select-selection': `
                            <template #sw-select-selection="{ searchInputAttrs }">
                                <span class="selected-value" @click.stop>Red</span>
                                <input class="search-input" v-bind="searchInputAttrs">
                                <button class="inner-button">+3</button>
                            </template>
                        `,
                    },
                },
            );

            const selection = wrapper.find('.sw-select__selection');
            selection.element.focus();
            await flushPromises();

            return selection;
        }

        function dispatchKey(type, element, init) {
            const event = new KeyboardEvent(type, { bubbles: true, cancelable: true, ...init });
            element.dispatchEvent(event);

            return event;
        }

        function dispatchKeydown(element, init) {
            return dispatchKey('keydown', element, init);
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

        it('should show the focus while the select is open and drop it once the focus left the closed select', async () => {
            const selection = await createFocusedSelection();
            dispatchKeydown(selection.element, { key: 'ArrowDown' });
            wrapper.find('.search-input').element.focus();
            await flushPromises();

            expect(wrapper.find('.sw-select').classes()).toContain('has--focus');

            dispatchKeydown(wrapper.find('.search-input').element, { key: 'Escape' });
            selection.element.blur();
            await flushPromises();

            expect(wrapper.find('.sw-select').classes()).not.toContain('has--focus');
        });

        it('should keep the search input out of the tab order', async () => {
            await createFocusedSelection();

            expect(wrapper.find('.search-input').attributes('tabindex')).toBe('-1');
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

        it.each([
            ['a plain character', { key: 'a' }],
            ['an AltGr character', { key: '@', ctrlKey: true, altKey: true }],
            ['an Option character', { key: '@', altKey: true }],
        ])('should expand on %s and type it over the text of the search', async (_, init) => {
            const selection = await createFocusedSelection();
            const searchInput = wrapper.find('.search-input').element;
            searchInput.value = 'Previous selection';
            const onInput = jest.fn();
            searchInput.addEventListener('input', onInput);

            dispatchKeydown(selection.element, init);
            await flushPromises();
            searchInput.removeEventListener('input', onInput);

            expect(selection.attributes('aria-expanded')).toBe('true');
            expect(document.activeElement).toBe(searchInput);
            expect(searchInput.value).toBe(init.key);
            expect(onInput).toHaveBeenCalledTimes(1);
        });

        it.each([
            ['Backspace', {}],
            ['Delete', {}],
            ['Backspace', { ctrlKey: true }],
            ['Backspace', { metaKey: true }],
        ])('should emit "delete-key" on "%s" %o and stay collapsed', async (key, modifiers) => {
            const selection = await createFocusedSelection();

            const event = dispatchKeydown(selection.element, { key, ...modifiers });
            await flushPromises();

            expect(selection.attributes('aria-expanded')).toBe('false');
            expect(event.defaultPrevented).toBe(true);
            expect(wrapper.emitted('delete-key')).toHaveLength(1);
        });

        it('should not emit "delete-key" on a disabled select that got the focus by a click', async () => {
            const selection = await createFocusedSelection({}, { disabled: true });

            dispatchKeydown(selection.element, { key: 'Backspace' });
            await flushPromises();

            expect(wrapper.emitted('delete-key')).toBeUndefined();
        });

        describe('where ALT is the system key', () => {
            it.each([
                ['an ALT shortcut', { key: 's', altKey: true }, 'false'],
                ['an AltGr character', { key: '@', ctrlKey: true, altKey: true }, 'true'],
            ])('should handle %s', async (_, init, expanded) => {
                const selection = await createFocusedSelection({
                    $device: { getSystemKey: () => 'ALT' },
                });

                dispatchKeydown(selection.element, init);
                await flushPromises();

                expect(selection.attributes('aria-expanded')).toBe(expanded);
            });
        });

        it('should leave a read-only search untouched when typing opens the select', async () => {
            const selection = await createFocusedSelection();
            const searchInput = wrapper.find('.search-input').element;
            searchInput.readOnly = true;

            dispatchKeydown(selection.element, { key: 'a' });
            await flushPromises();

            expect(selection.attributes('aria-expanded')).toBe('true');
            expect(searchInput.value).toBe('');
        });

        it.each([
            ['CTRL+S', { key: 's', ctrlKey: true }],
            ['CMD+S', { key: 's', metaKey: true }],
            ['Shift', { key: 'Shift' }],
            ['a composing key', { key: 'a', isComposing: true }],
        ])('should stay collapsed on %s', async (_, init) => {
            const selection = await createFocusedSelection();

            dispatchKeydown(selection.element, init);
            await flushPromises();

            expect(selection.attributes('aria-expanded')).toBe('false');
        });

        it.each([
            ' ',
            'Enter',
            'ArrowDown',
            'a',
        ])('should leave a keydown of "%s" on a button inside the select to that button', async (key) => {
            await createFocusedSelection();
            const innerButton = wrapper.find('.inner-button');
            innerButton.element.focus();

            const event = dispatchKeydown(innerButton.element, { key });
            await flushPromises();

            expect(wrapper.find('.sw-select__selection').attributes('aria-expanded')).toBe('false');
            expect(event.defaultPrevented).toBe(false);
        });

        it('should expand on a click on a selected value that stops its click from bubbling', async () => {
            const selection = await createFocusedSelection();

            wrapper.find('.selected-value').element.click();
            await flushPromises();

            expect(selection.attributes('aria-expanded')).toBe('true');
        });

        it('should stay collapsed on a click on a button inside the select', async () => {
            const selection = await createFocusedSelection();

            wrapper.find('.inner-button').element.click();
            await flushPromises();

            expect(selection.attributes('aria-expanded')).toBe('false');
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

        it('should not click the previous element on SHIFT+TAB', async () => {
            const previousLink = document.createElement('a');
            previousLink.href = '#/sw/product/index';
            const onClick = jest.fn((event) => event.preventDefault());
            previousLink.onclick = onClick;
            document.body.prepend(previousLink);
            const selection = await createFocusedSelection();
            dispatchKeydown(selection.element, { key: 'ArrowDown' });
            const searchInput = wrapper.find('.search-input').element;
            searchInput.focus();

            dispatchKeydown(searchInput, { key: 'Tab', shiftKey: true });
            await flushPromises();

            expect(onClick).not.toHaveBeenCalled();
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
