import NativeEventEmitter from 'src/helper/emitter.helper';

describe('Storefront main entry point', () => {
    beforeEach(async () => {
        global.themeJsPublicPath = 'public/theme/#theme-hash#';

        window.validationMessages = {
            required: 'Input should not be empty.',
            email: 'Invalid email address.',
            confirmation: 'Confirmation field does not match.',
            minLength: 'Input is too short.',
        };

        await import('src/main');
    });

    it('should have all needed window properties', () => {
        expect(window.bootstrap).toBeDefined();
        expect(typeof window.bootstrap).toBe('object');

        expect(window.eventEmitter).toBeInstanceOf(NativeEventEmitter);

        expect(window.Feature).toBeDefined();
        expect(typeof window.Feature).toBe('function');

        expect(window.PluginManager).toBeDefined();
        expect(window.PluginBaseClass).toBeDefined();

        expect(window.formValidation).toBeDefined();
    });
});
