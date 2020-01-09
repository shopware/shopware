/* eslint-disable */
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

    it('should return configuration object or undefined when calling "getScssEntryByName"', () => {
        // theme-files.json without overrides.scss entry point
        let themeFilesJson = require('./assets/theme-files.mock');
        let result = utils.getScssEntryByName(themeFilesJson.style, 'scss/overrides.scss');

        expect(result).toBeUndefined();

        // theme-files.json with overrides.scss entry point
        themeFilesJson = require('./assets/theme-files-override.mock.json');
        result = utils.getScssEntryByName(themeFilesJson.style, 'scss/overrides.scss');

        expect(result).toBeDefined();
        expect(result).toHaveProperty('filepath');
        expect(result).toHaveProperty('resolveMapping');
        expect(result).toHaveProperty('extensions');
    });
});
