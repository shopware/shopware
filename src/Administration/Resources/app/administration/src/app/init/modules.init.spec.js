/**
 * @package admin
 */
import initializeAppModules from 'src/app/init/modules.init';
import baseModules from 'src/module';

jest.mock('src/module', () => {
    return jest.fn(() => 'works');
});

describe('src/app/init/modules.init.ts', () => {
    it('should call the base modules', () => {
        initializeAppModules();

        expect(baseModules).toHaveBeenCalled();
    });
});
