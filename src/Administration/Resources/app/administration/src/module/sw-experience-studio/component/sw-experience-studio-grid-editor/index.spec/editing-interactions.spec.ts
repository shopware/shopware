import { createWrapper, layout } from './helpers';

describe('grid editor edge interactions', () => {
    let wrapper: ReturnType<typeof createWrapper>;

    afterEach(() => wrapper?.unmount());

    async function clickDisabledControl(selector: string) {
        const control = wrapper.get(selector);
        control.element.removeAttribute('disabled');
        await control.trigger('click');
    }

    it('uses the default grid when xs is missing and keeps read-only controls inert', async () => {
        wrapper = createWrapper({ gridLayout: {} });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(1);
        wrapper.unmount();

        wrapper = createWrapper({
            allowEdit: false,
            gridLayout: layout('percent:20 auto|a1 a2'),
        });
        await clickDisabledControl('.sw-experience-studio-grid-editor__area');
        await clickDisabledControl('.sw-experience-studio-grid-editor__add button');
        await clickDisabledControl('.sw-experience-studio-grid-editor__rows button');
        await clickDisabledControl('.sw-experience-studio-grid-editor__actions button');
        await clickDisabledControl('.sw-experience-studio-grid-editor__actions button:last-child');

        const type = wrapper.get('.sw-experience-studio-grid-editor__column-type');
        type.element.removeAttribute('disabled');
        await type.setValue('px');

        const input = wrapper.get('.sw-experience-studio-grid-editor__column input');
        input.element.removeAttribute('disabled');
        await input.setValue('30');

        const smTab = wrapper.findAll('.sw-experience-studio-grid-editor__breakpoints button')[1];
        expect(smTab.attributes('disabled')).toBeUndefined();
        await smTab.trigger('click');
        expect(smTab.attributes('aria-pressed')).toBe('true');
        await clickDisabledControl('.sw-experience-studio-grid-editor__inheritance');
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();

        wrapper.unmount();
        wrapper = createWrapper({ gridLayout: layout('percent:101 auto|a1 a2') });
        await wrapper.findAll('.sw-experience-studio-grid-editor__breakpoints button')[1].trigger('click');
        await clickDisabledControl('.sw-experience-studio-grid-editor__inheritance');
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
        await clickDisabledControl('.sw-experience-studio-grid-editor__rows button');
        const columnButtons = wrapper.get('.sw-experience-studio-grid-editor__column-entry').findAll('button');
        for (const button of columnButtons.slice(1)) {
            button.element.removeAttribute('disabled');
            await button.trigger('click');
        }
        await clickDisabledControl('.sw-experience-studio-grid-editor__actions button');
        await clickDisabledControl('.sw-experience-studio-grid-editor__actions button:last-child');
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
    });

    it('ignores restore confirmation if editing is disabled while the modal is open', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto|a1~sm=auto|a1') });
        await wrapper.findAll('.sw-experience-studio-grid-editor__breakpoints button')[1].trigger('click');
        await wrapper.get('.sw-experience-studio-grid-editor__inheritance').trigger('click');
        expect(wrapper.find('.sw-experience-studio-grid-editor__modal-footer').exists()).toBe(true);

        await wrapper.setProps({ allowEdit: false });
        await clickDisabledControl('.sw-experience-studio-grid-editor__modal-footer button:last-child');
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
    });

    it('reorders columns only after the pointer crosses the target midpoint', async () => {
        wrapper = createWrapper({ gridLayout: layout('px:100 auto|a1 a2') });
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

            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 6 }));
            document.dispatchEvent(new MouseEvent('mouseup'));
            expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([layout('auto px:100|a1 a2')]);
        } finally {
            if (originalElementFromPoint) Object.defineProperty(document, 'elementFromPoint', originalElementFromPoint);
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
