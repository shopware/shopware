const {
    Application
} = Shopware;

describe('core/application.js', () => {
    it('should be error tolerant if loading a plugin\'s files fails', async () => {
        Application.injectJs = async () => {
            throw new Error('Inject js fails');
        };

        const result = await Application.injectPlugin({
            js: ['some.js']
        });

        expect(result).toBeNull();
    });
});
