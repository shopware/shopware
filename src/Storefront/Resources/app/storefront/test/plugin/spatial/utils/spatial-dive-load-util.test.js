import { loadDIVE } from 'src/plugin/spatial/utils/spatial-dive-load-util';

jest.mock('@shopware-ag/dive', () => ({ DIVE: {} }));
jest.mock('@shopware-ag/dive/state', () => ({ State: {} }));
jest.mock('@shopware-ag/dive/ar', () => ({ ARSystem: {} }));

/**
 * @package innovation
 */
describe('loadDIVE', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        window.DIVEClass = undefined;
        window.DIVEARPlugin = undefined;
        window.loadDiveUtil = undefined;
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('should load dive', async () => {
        expect(window.DIVEClass).toBeUndefined();
        expect(window.DIVEARPlugin).toBeUndefined();
        expect(window.loadDiveUtil).toBeUndefined();

        await loadDIVE();

        expect(typeof window.DIVEClass).toBe('object');
        expect(typeof window.DIVEARPlugin).toBe('object');
        expect(typeof window.loadDiveUtil.promise).toBe('object');
    });

    test('should not load dive if promise is already resolved', async () => {
        window.DIVEClass = 'dive';

        await loadDIVE();

        expect(window.DIVEClass).toBe('dive');
    });

    test('should not load dive if ARPlugin is already loaded', async () => {
        window.DIVEARPlugin = 'arPlugin';

        await loadDIVE();

        expect(window.DIVEARPlugin).toBe('arPlugin');
    });

    test('should not run import when dive is already loading', async () => {
        const testPromise = new Promise((resolve) => { resolve(); });
        window.loadDiveUtil = {
            promise: testPromise,
        }

        await loadDIVE();

        expect(window.loadDiveUtil.promise).toBe(testPromise);
    });
});
