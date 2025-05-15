import { loadDIVE } from 'src/plugin/spatial/utils/spatial-dive-load-util';

jest.mock('@shopware-ag/dive', () => ({ DIVE: {} }));
jest.mock('@shopware-ag/dive/modules/State', () => ({ State: {} }));

/**
 * @package innovation
 */
describe('loadDIVE', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        window.DIVEClass = undefined;
        window.ARSystem = undefined;
        window.loadDiveUtil = undefined;
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('should load dive', async () => {
        expect(window.DIVEClass).toBeUndefined();
        expect(window.ARSystem).toBeUndefined();
        expect(window.loadDiveUtil).toBeUndefined();

        await loadDIVE();

        expect(typeof window.DIVEClass).toBe('object');
        expect(typeof window.ARSystem).toBe('object');
        expect(typeof window.loadDiveUtil.promise).toBe('object');
    });

    test('should not load dive if promise is already resolved', async () => {
        window.DIVEClass = 'dive';

        await loadDIVE();

        expect(window.DIVEClass).toBe('dive');
    });

    test('should not load dive if ARSystem is already loaded', async () => {
        window.ARSystem = 'arSystem';

        await loadDIVE();

        expect(window.ARSystem).toBe('arSystem');
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
