import { nextTick } from 'vue';
import BreakpointSelect from '../../sw-experience-studio-breakpoint-select';
import { createWrapper, layout, selectBreakpoint } from './helpers';

describe('grid editor edge interactions', () => {
    let wrapper: ReturnType<typeof createWrapper>;

    afterEach(() => wrapper?.unmount());

    async function clickDisabledControl(selector: string) {
        const control = wrapper.get(selector);
        control.element.removeAttribute('disabled');
        await control.trigger('click');
    }

    it('uses the supplied fallback order to resolve a grid without a local override', async () => {
        wrapper = createWrapper({
            gridLayout: layout('auto|a1~md=auto auto|a1 a2'),
            breakpointFallbacks: { xs: ['md', 'lg'] },
        });

        await selectBreakpoint(wrapper, 'xs');
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__column-entry')).toHaveLength(2);
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();

        await selectBreakpoint(wrapper, 'sm');
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__column-entry')).toHaveLength(1);
    });

    it('uses the default grid at a breakpoint when no fallback metadata is available', async () => {
        wrapper = createWrapper({
            gridLayout: layout('auto auto|a1 a2'),
            breakpointFallbacks: undefined,
        });

        expect(wrapper.findAll('.sw-experience-studio-grid-editor__column-entry')).toHaveLength(2);

        await selectBreakpoint(wrapper, 'sm');

        expect(wrapper.findAll('.sw-experience-studio-grid-editor__column-entry')).toHaveLength(1);
        expect(wrapper.getComponent(BreakpointSelect).props('overrides')).toEqual([]);
    });

    it('ignores reset while dragging an override', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto|a1~sm=px:100 auto|a1 a2') });
        await selectBreakpoint(wrapper, 'sm');
        const selector = wrapper.getComponent(BreakpointSelect);
        await wrapper.get('.sw-experience-studio-grid-editor__column-entry').trigger('dragstart');

        expect(selector.props('canReset')).toBe(false);
        selector.vm.$emit('reset');
        document.dispatchEvent(new MouseEvent('mouseup'));
        await nextTick();

        expect(selector.props('overrides')).toEqual(['sm']);
        expect(selector.props('canReset')).toBe(true);
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
    });

    it('keeps a single-column grid out of drag mode', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto|a1') });

        await wrapper.get('.sw-experience-studio-grid-editor__column-entry').trigger('dragstart');

        expect(wrapper.getComponent(BreakpointSelect).props('canReset')).toBe(true);
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
    });

    it('uses the default grid when lg is missing and keeps read-only controls inert', async () => {
        wrapper = createWrapper({ gridLayout: {} });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__column')).toHaveLength(1);
        wrapper.unmount();

        wrapper = createWrapper({
            allowEdit: false,
            gridLayout: layout('percent:20 auto|a1 a2'),
        });
        await clickDisabledControl('.sw-experience-studio-grid-editor__area');
        await clickDisabledControl('.sw-experience-studio-grid-editor__add button');
        await clickDisabledControl('.sw-experience-studio-grid-editor__rows button');
        expect(wrapper.find('.sw-experience-studio-grid-editor__actions').exists()).toBe(false);

        const type = wrapper.get('.sw-experience-studio-grid-editor__column-type');
        type.element.removeAttribute('disabled');
        await type.setValue('px');

        const input = wrapper.get('.sw-experience-studio-grid-editor__column input');
        input.element.removeAttribute('disabled');
        await input.setValue('30');

        await selectBreakpoint(wrapper, 'sm');
        expect(wrapper.getComponent({ name: 'BreakpointSelect' }).props('modelValue')).toBe('sm');
        await clickDisabledControl('.sw-experience-studio-grid-editor__add button');
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
    });

    it('keeps disabled track controls inert at the row and column limits', async () => {
        wrapper = createWrapper({
            gridLayout: layout(
                `${Array(12).fill('auto').join(' ')}|${Array(12).fill(Array(12).fill('a1').join(' ')).join(';')}`,
            ),
        });
        for (const button of wrapper.findAll('.sw-experience-studio-grid-editor__add button')) {
            button.element.removeAttribute('disabled');
            await button.trigger('click');
        }
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();

        wrapper.unmount();
        wrapper = createWrapper({ gridLayout: layout('auto|a1') });
        const columnButtons = wrapper.get('.sw-experience-studio-grid-editor__column-entry').findAll('button');
        for (const button of columnButtons.slice(1)) {
            button.element.removeAttribute('disabled');
            await button.trigger('click');
        }
        await wrapper.setProps({ gridLayout: layout('auto auto|a1 a2') });
        await clickDisabledControl('.sw-experience-studio-grid-editor__rows button');
        await wrapper.get('.sw-experience-studio-grid-editor__area').trigger('click');
        await clickDisabledControl('.sw-experience-studio-grid-editor__actions button');
        await clickDisabledControl('.sw-experience-studio-grid-editor__actions button:last-child');
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
    });

    it('creates an override only after a drag crosses the target midpoint', async () => {
        wrapper = createWrapper({ gridLayout: layout('px:100 auto|a1 a2') });
        await selectBreakpoint(wrapper, 'sm');
        const rows = wrapper.findAll('.sw-experience-studio-grid-editor__column-entry');
        const originalElementFromPoint = Object.getOwnPropertyDescriptor(document, 'elementFromPoint');
        Object.defineProperty(document, 'elementFromPoint', {
            configurable: true,
            value: () => rows[1].element,
        });
        Object.defineProperty(rows[1].element, 'getBoundingClientRect', {
            configurable: true,
            value: () => ({ top: 0, bottom: 10, left: 0, right: 10, width: 10, height: 10, x: 0, y: 0, toJSON: () => ({}) }),
        });

        try {
            await rows[0].trigger('dragstart');
            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 1 }));
            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 2 }));
            expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
            expect(wrapper.getComponent({ name: 'BreakpointSelect' }).props('overrides')).toEqual([]);

            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 6 }));
            document.dispatchEvent(new MouseEvent('mouseup'));
            expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([layout('px:100 auto|a1 a2~sm=auto px:100|a1 a2')]);
        } finally {
            if (originalElementFromPoint) Object.defineProperty(document, 'elementFromPoint', originalElementFromPoint);
            else Reflect.deleteProperty(document, 'elementFromPoint');
        }
    });

    it('removes the temporary override when a drag returns to its inherited order', async () => {
        const baseLayout = layout('px:100 auto|a1 a2');
        wrapper = createWrapper({ gridLayout: baseLayout });
        await selectBreakpoint(wrapper, 'sm');

        const [first, second] = wrapper.findAll('.sw-experience-studio-grid-editor__column-entry');
        const original = Object.getOwnPropertyDescriptor(document, 'elementFromPoint');
        Object.defineProperty(document, 'elementFromPoint', {
            configurable: true,
            value: () => second.element,
        });
        const secondBounds = jest.spyOn(second.element, 'getBoundingClientRect').mockReturnValue(new DOMRect(0, 0, 10, 10));

        try {
            await first.trigger('dragstart');
            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 1 }));
            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 6 }));
            await nextTick();
            expect(wrapper.getComponent(BreakpointSelect).props('overrides')).toEqual(['sm']);

            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 4 }));
            document.dispatchEvent(new MouseEvent('mouseup'));
            await nextTick();

            expect(wrapper.getComponent(BreakpointSelect).props('overrides')).toEqual([]);
            expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
        } finally {
            secondBounds.mockRestore();
            if (original) Object.defineProperty(document, 'elementFromPoint', original);
            else Reflect.deleteProperty(document, 'elementFromPoint');
        }
    });

    it('handles upward drags, same-column drops, and touch movement without a pointer', async () => {
        wrapper = createWrapper({ gridLayout: layout('px:100 auto|a1 a2') });
        const rows = wrapper.findAll('.sw-experience-studio-grid-editor__column-entry');
        const originalElementFromPoint = Object.getOwnPropertyDescriptor(document, 'elementFromPoint');
        Object.defineProperty(document, 'elementFromPoint', {
            configurable: true,
            value: () => rows[0].element,
        });
        Object.defineProperty(rows[0].element, 'getBoundingClientRect', {
            configurable: true,
            value: () => ({ top: 0, bottom: 10, left: 0, right: 10, width: 10, height: 10, x: 0, y: 0, toJSON: () => ({}) }),
        });

        try {
            await rows[0].trigger('dragstart');
            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 1 }));
            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 6 }));
            document.dispatchEvent(new MouseEvent('mouseup'));
            expect(wrapper.emitted('update:gridLayout')).toBeUndefined();

            await rows[1].trigger('dragstart');
            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 6 }));
            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 4 }));
            document.dispatchEvent(new MouseEvent('mouseup'));
            expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([layout('auto px:100|a1 a2')]);
        } finally {
            if (originalElementFromPoint) Object.defineProperty(document, 'elementFromPoint', originalElementFromPoint);
            else Reflect.deleteProperty(document, 'elementFromPoint');
        }

        wrapper.unmount();
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2') });
        const row = wrapper.get('.sw-experience-studio-grid-editor__column-entry');
        const originalElement = Object.getOwnPropertyDescriptor(document, 'elementFromPoint');
        Object.defineProperty(document, 'elementFromPoint', { configurable: true, value: () => document.body });

        try {
            await row.trigger('dragstart');
            const touchMove = new Event('touchmove');
            Object.defineProperty(touchMove, 'touches', { value: [] });
            document.dispatchEvent(touchMove);
            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 1 }));
            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 2 }));
            document.dispatchEvent(new MouseEvent('mouseup'));
            expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
        } finally {
            if (originalElement) Object.defineProperty(document, 'elementFromPoint', originalElement);
            else Reflect.deleteProperty(document, 'elementFromPoint');
        }
    });

    it('reorders with touch coordinates and cleans listeners on unmount', async () => {
        wrapper = createWrapper({ gridLayout: layout('px:100 auto|a1 a2') });
        const rows = wrapper.findAll('.sw-experience-studio-grid-editor__column-entry');
        const original = Object.getOwnPropertyDescriptor(document, 'elementFromPoint');
        Object.defineProperty(document, 'elementFromPoint', { configurable: true, value: () => rows[1].element });
        Object.defineProperty(rows[1].element, 'getBoundingClientRect', {
            configurable: true,
            value: () => ({ top: 0, height: 10 }),
        });
        const remove = jest.spyOn(document, 'removeEventListener');
        try {
            await rows[0].trigger('dragstart');
            for (const y of [1, 6]) {
                const event = new Event('touchmove');
                Object.defineProperty(event, 'touches', { value: [{ clientX: 1, clientY: y }] });
                document.dispatchEvent(event);
            }
            document.dispatchEvent(new Event('touchend'));
            expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([layout('auto px:100|a1 a2')]);
            await rows[0].trigger('dragstart');
            wrapper.unmount();
            expect(remove).toHaveBeenCalledWith('touchmove', expect.any(Function));
            expect(remove).toHaveBeenCalledWith('touchcancel', expect.any(Function));
        } finally {
            if (original) Object.defineProperty(document, 'elementFromPoint', original);
            else Reflect.deleteProperty(document, 'elementFromPoint');
            remove.mockRestore();
        }
    });

    it.each(['touchcancel', 'unmount'])('cleans the real directive after %s', (interruption) => {
        jest.useFakeTimers();
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2') }, true);
        const row = wrapper.get('.sw-experience-studio-grid-editor__column-entry');
        const element = row.element;
        const event = new TouchEvent('touchstart', { bubbles: true });
        Object.defineProperty(event, 'touches', { value: [{ pageX: 10, pageY: 10 }] });
        element.dispatchEvent(event);
        jest.advanceTimersByTime(100);
        expect(element.classList.contains('is--dragging')).toBe(true);
        expect(document.querySelector('.is--drag-element')).not.toBeNull();
        if (interruption === 'touchcancel') document.dispatchEvent(new Event('touchcancel'));
        else wrapper.unmount();
        expect(element.classList.contains('is--dragging')).toBe(false);
        expect(document.querySelector('.is--drag-element')).toBeNull();
        jest.useRealTimers();
    });

    it('rejects a pixel-only column and accepts a numeric fraction value', async () => {
        wrapper = createWrapper({ gridLayout: layout('px:300|a1') });
        const select = wrapper.get('.sw-experience-studio-grid-editor__column-type');
        select.find('option[value="px"]').element.removeAttribute('disabled');
        (select.element as HTMLSelectElement).value = 'px';
        await select.trigger('change');
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();

        wrapper.unmount();
        wrapper = createWrapper({ gridLayout: layout('auto|a1') });
        await wrapper.get('.sw-experience-studio-grid-editor__column-type').setValue('fraction');
        await wrapper.get('.sw-experience-studio-grid-editor__column select').setValue('4');
        expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([layout('fraction:4|a1')]);
    });
});
