/**
 * @sw-package framework
 */

import { registerShortcut } from './shortcut-registry.helper';

function press(key: string, init: KeyboardEventInit = {}): void {
    document.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, ...init }));
}

function register(key: string, handler: () => void, active: () => boolean = () => true): () => void {
    return registerShortcut({ key, handler, active, systemKey: () => 'CTRL' });
}

describe('src/core/helper/shortcut-registry.helper', () => {
    let unregisterFunctions: (() => void)[] = [];

    const add = (key: string, handler: () => void, active?: () => boolean): void => {
        unregisterFunctions.push(register(key, handler, active));
    };

    afterEach(() => {
        unregisterFunctions.forEach((unregister) => unregister());
        unregisterFunctions = [];
        jest.restoreAllMocks();
    });

    it('fires a system shortcut when the system key is held', () => {
        const onSave = jest.fn();

        add('SYSTEMKEY+S', onSave);

        press('s');
        expect(onSave).not.toHaveBeenCalled();

        press('s', { ctrlKey: true });
        expect(onSave).toHaveBeenCalledTimes(1);
    });

    it('fires a multi-key sequence only once both keys arrive', () => {
        const openFilters = jest.fn();

        add('OF', openFilters);

        press('o');
        expect(openFilters).not.toHaveBeenCalled();

        press('f');
        expect(openFilters).toHaveBeenCalledTimes(1);
    });

    it('prefers a sequence over a single key that would also match', () => {
        const openFilters = jest.fn();
        const focusSearch = jest.fn();

        add('f', focusSearch);
        add('OF', openFilters);

        press('o');
        press('f');

        expect(openFilters).toHaveBeenCalledTimes(1);
        expect(focusSearch).not.toHaveBeenCalled();

        press('f');
        expect(focusSearch).toHaveBeenCalledTimes(1);
    });

    it('forgets a half-typed sequence after the keystroke delay', () => {
        jest.useFakeTimers();
        const openFilters = jest.fn();

        add('OF', openFilters);

        press('o');
        jest.advanceTimersByTime(1000);
        press('f');

        expect(openFilters).not.toHaveBeenCalled();
        jest.useRealTimers();
    });

    it('does not fire a shortcut whose active() says no', () => {
        const inactive = jest.fn();

        add('ESCAPE', inactive, () => false);

        press('Escape');

        expect(inactive).not.toHaveBeenCalled();
    });

    it('ignores plain keys typed into an input, but not system shortcuts', () => {
        const onSave = jest.fn();
        const focusSearch = jest.fn();
        const input = document.createElement('input');

        document.body.appendChild(input);
        add('SYSTEMKEY+S', onSave);
        add('f', focusSearch);

        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'f', bubbles: true }));
        expect(focusSearch).not.toHaveBeenCalled();

        input.dispatchEvent(new KeyboardEvent('keydown', { key: 's', ctrlKey: true, bubbles: true }));
        expect(onSave).toHaveBeenCalledTimes(1);

        document.body.removeChild(input);
    });

    it('ignores every keystroke that originates inside a modal', () => {
        const onSave = jest.fn();
        const modal = document.createElement('div');
        const button = document.createElement('button');

        modal.classList.add('sw-modal');
        modal.appendChild(button);
        document.body.appendChild(modal);
        add('SYSTEMKEY+S', onSave);

        button.dispatchEvent(new KeyboardEvent('keydown', { key: 's', ctrlKey: true, bubbles: true }));

        expect(onSave).not.toHaveBeenCalled();
        document.body.removeChild(modal);
    });

    it('ignores every keystroke while the shortcut service reports shortcuts as disabled', () => {
        const onSave = jest.fn();

        jest.spyOn(Shopware, 'Service').mockReturnValue({ isShortcutsDisabled: () => true } as never);
        add('SYSTEMKEY+S', onSave);

        press('s', { ctrlKey: true });

        expect(onSave).not.toHaveBeenCalled();
    });

    it('removes only the registration its own unregister function belongs to', () => {
        const first = jest.fn();
        const second = jest.fn();

        const unregisterFirst = register('SYSTEMKEY+S', first);

        add('SYSTEMKEY+S', second);
        unregisterFirst();

        press('s', { ctrlKey: true });

        expect(first).not.toHaveBeenCalled();
        expect(second).toHaveBeenCalledTimes(1);
    });
});
