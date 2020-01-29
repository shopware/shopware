/* eslint-disable */
const utils = require('../../build/utils');
const fs = require('fs');
jest.mock('fs');

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

    describe('finding SCSS entry from theme-files.json', () => {
        it('should return undefined when passing theme-files.json without overrides.scss', () => {
            const themeFilesJson = require('./assets/theme-files.mock');
            const result = utils.getScssEntryByName(themeFilesJson.style, 'scss/overrides.scss');

            expect(result).toBeUndefined();
        });

        it('should return entry object when passing theme-files.json with overrides.scss', () => {
            const themeFilesJson = require('./assets/theme-files-override.mock.json');
            const result = utils.getScssEntryByName(themeFilesJson.style, 'scss/overrides.scss');

            expect(result).toBeDefined();
            expect(result).toHaveProperty('filepath');
            expect(result).toHaveProperty('resolveMapping');
            expect(result).toHaveProperty('extensions');
        });
    });

    describe('extend SCSS resources with overrides.scss path', () => {
        it('should return modified array with correct paths when passing entry object', () => {
            const entry = {
                filepath: '/app/custom/plugins/CustomTheme/src/Resources/app/storefront/src/scss/overrides.scss',
                resolveMapping: [],
                extensions: []
            };

            fs.existsSync.mockReturnValue(true);

            const log = jest.spyOn(global.console, 'log');
            const result = utils.getScssResources(
                [
                    '/app/var/theme-variables.scss',
                    '/app/platform/src/Storefront/Resources/app/storefront/src/scss/variables.scss',
                ],
                entry,
                'overrides.scss'
            );

            expect(fs.existsSync).toHaveBeenCalled();
            expect(log).toHaveBeenCalledWith('> An overrides.scss was found. Adding to SASS resources...\n');
            expect(result).toEqual([
                '/app/custom/plugins/CustomTheme/src/Resources/app/storefront/src/scss/overrides.scss',
                '/app/var/theme-variables.scss',
                '/app/platform/src/Storefront/Resources/app/storefront/src/scss/variables.scss'
            ]);
        });

        it('should return the same array with correct paths when passing undefined entry', () => {
            const log = jest.spyOn(global.console, 'log');
            const result = utils.getScssResources(
                [
                    '/app/var/theme-variables.scss',
                    '/app/platform/src/Storefront/Resources/app/storefront/src/scss/variables.scss',
                ],
                undefined,
                'overrides.scss'
            );

            expect(fs.existsSync).toHaveBeenCalledTimes(0);
            expect(log).toHaveBeenCalledWith('> No overrides.scss was found. Skipping...\n');
            expect(result).toEqual([
                '/app/var/theme-variables.scss',
                '/app/platform/src/Storefront/Resources/app/storefront/src/scss/variables.scss'
            ]);
        });
    });
});
