import initPreIndex from 'src/app/init-pre/index';

describe('src/app/init-pre/index.ts', () => {
    it('should contain pre-initializer', () => {
        expect(Object.keys(initPreIndex)).toHaveLength(2);
        expect(initPreIndex.apiServices).toBeDefined();
        expect(initPreIndex.state).toBeDefined();
    });
});
