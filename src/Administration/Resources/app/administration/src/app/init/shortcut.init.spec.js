/**
 * @package admin
 */
import initializeShortcutService from 'src/app/init/shortcut.init';

describe('src/app/init/shortcut.init.ts', () => {
    let result;

    beforeAll(() => {
        Shopware.Service().register('loginService', () => {
            return {
                isLoggedIn: () => true,
                addOnLogoutListener: jest.fn(() => true),
            };
        });

        Shopware.Service().register('shortcutService', () => {
            return {
                startEventListener: jest.fn(),
            };
        });

        result = initializeShortcutService();
    });

    it('should init the shortcut service', () => {
        expect(result).toEqual(
            expect.objectContaining({
                getPathByCombination: expect.any(Function),
                getShortcutRegistry: expect.any(Function),
                register: expect.any(Function),
            }),
        );
    });
});
