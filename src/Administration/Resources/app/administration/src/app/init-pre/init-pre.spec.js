/**
 * @package admin
 */
import initPreIndex from 'src/app/init-pre/index';

describe('src/app/init-pre/index.ts', () => {
    it('should contain pre-initializer', () => {
        expect(Object.keys(initPreIndex)).toHaveLength(3);
        expect(initPreIndex.apiServices).toBeDefined();
        expect(initPreIndex.state).toBeDefined();
        expect(initPreIndex.store).toBeDefined();
    });
});
