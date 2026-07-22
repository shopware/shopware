/**
 * @sw-package framework
 */

import createShortcutService from 'src/app/service/shortcut.service';

const shortcutsDisabledStorageKey = 'sw-admin-keyboard-shortcuts-disabled';

describe('app/service/shortcut.service', () => {
    let shortcutService;

    const shortcutFactory = {
        getPathByCombination: jest.fn(),
    };

    beforeEach(() => {
        shortcutService = null;
        localStorage.removeItem(shortcutsDisabledStorageKey);
    });

    afterEach(() => {
        shortcutService?.stopEventListener();
        shortcutFactory.getPathByCombination.mockReset();
        localStorage.removeItem(shortcutsDisabledStorageKey);
        jest.restoreAllMocks();
    });

    it('should enable shortcuts by default', () => {
        shortcutService = createShortcutService(shortcutFactory);

        expect(shortcutService.isShortcutsDisabled()).toBe(false);
    });

    it('should persist disabled shortcuts', () => {
        const removeEventListenerSpy = jest.spyOn(document, 'removeEventListener');
        shortcutService = createShortcutService(shortcutFactory);

        shortcutService.setShortcutsDisabled(true);

        expect(shortcutService.isShortcutsDisabled()).toBe(true);
        expect(localStorage.getItem(shortcutsDisabledStorageKey)).toBe('true');
        expect(removeEventListenerSpy).toHaveBeenCalledWith('keyup', expect.any(Function));
    });

    it('should not start the event listener when shortcuts are disabled', () => {
        localStorage.setItem(shortcutsDisabledStorageKey, 'true');
        const addEventListenerSpy = jest.spyOn(document, 'addEventListener');
        shortcutService = createShortcutService(shortcutFactory);

        shortcutService.startEventListener();

        expect(addEventListenerSpy).not.toHaveBeenCalled();
    });

    it('should start the event listener when shortcuts are enabled', () => {
        const addEventListenerSpy = jest.spyOn(document, 'addEventListener');
        shortcutService = createShortcutService(shortcutFactory);

        shortcutService.startEventListener();

        expect(addEventListenerSpy).toHaveBeenCalledWith('keyup', expect.any(Function));
    });
});
