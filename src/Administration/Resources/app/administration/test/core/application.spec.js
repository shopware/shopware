const {
    Application
} = Shopware;

describe('core/application.js', () => {
    it('should be error tolerant if loading a plugin\'s files fails', async () => {
        const warningSpy = jest.spyOn(console, 'warn').mockImplementation();

        Application.injectJs = async () => {
            throw new Error('Inject js fails');
        };

        const result = await Application.injectPlugin({
            js: ['some.js']
        });

        expect(warningSpy).toHaveBeenCalledWith('Error while loading plugin', { js: ['some.js'] });
        expect(result).toBeNull();
    });
});
