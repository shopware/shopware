/* eslint-disable */
const webpack = require('webpack');
const merge = require('webpack-merge');
const path = require('path');
const WebpackPluginInjector = require('@shopware-ag/webpack-plugin-injector');

// Fake plugins.json to inject our test entry point for size comparison of the resulting chunk
const entryPointConfig = {
    ExampleEntryPoint: {
        basePath: __dirname,
        views: [],
        technicalName: 'example-entry-point',
        storefront: {
            path: path.join(__dirname, 'assets'),
            entryFilePath: 'assets/entry-point.js',
            webpack: null,
            styleFiles: []
        }
    }
};

const prepareWebpackConfig = (mode = 'dev', injectTestPlugin = false) => {
    const path = `../../build/webpack.${mode}.config.js`;
    let webpackConfig = merge(
        require('../../build/webpack.base.config'),
        require(path) // eslint-disable-line
    );

    if (injectTestPlugin) {
        const injector = new WebpackPluginInjector(entryPointConfig, webpackConfig, 'storefront');
        webpackConfig = injector.webpackConfig;
    }
    return webpackConfig;
};

describe('webpack/chunk-splitting', () => {
    // We're running the test for dev & prod cause the changes have to be in place for both modes to work correctly
    ['dev', 'prod'].forEach((mode) => {
        it(`should contain the necessary webpack settings to have chunk splitting working (mode: ${mode})`, () => {
            const config = prepareWebpackConfig(mode);
            // console.log(config.optimization.splitChunks);
            expect(config.optimization.runtimeChunk.name).toBe('runtime');
            expect(Object.prototype.hasOwnProperty.call(config.optimization.splitChunks, 'cacheGroups')).toBeTruthy();
            expect(Object.prototype.hasOwnProperty.call(config.optimization.splitChunks.cacheGroups, 'vendor-node')).toBeTruthy();
            expect(Object.prototype.hasOwnProperty.call(config.optimization.splitChunks.cacheGroups, 'vendor-shared')).toBeTruthy();
        });
    });

    describe('webpack build', () => {
        beforeEach(() => {
            // Increase default timeout for the webpack build
            jest.setTimeout(1000000);
        });

        it(`should build successfully with an additional entry point in place`, (done) => {
            const config = prepareWebpackConfig('prod', true);

            webpack(config, (err, stats) => {
                const info = stats.toJson();
                if (err || stats.hasErrors()) {
                    done(err);
                    return;
                }

                const assets = info.assets;
                const testChunk = assets.find((chunkInfo) => {
                    return chunkInfo.name === './js/example-entry-point.js';
                });

                expect(testChunk.size <= 25000).toBeTruthy();
                done();
            });
        });
    });
});
