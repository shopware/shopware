const utils = require('../../build/utils');

describe('webpack/utils', () => {
    it('should contain all functions', () => {
        expect(utils).toHaveProperty('getScssEntryByName');
        expect(utils).toHaveProperty('getBuildPath');
        expect(utils).toHaveProperty('getPublicPath');
        expect(utils).toHaveProperty('getProjectRootPath');
        expect(utils).toHaveProperty('getPath');
        expect(utils).toHaveProperty('getMode');
        expect(utils).toHaveProperty('getOutputPath');
        expect(utils).toHaveProperty('getAppUrl');
        expect(utils).toHaveProperty('getScssEntryByName');
        expect(utils).toHaveProperty('getScssResources');
        expect(utils).toHaveProperty('isHotModuleReplacementMode');
        expect(utils).toHaveProperty('isDevelopmentEnvironment');
        expect(utils).toHaveProperty('isProductionEnvironment');
    });
});
