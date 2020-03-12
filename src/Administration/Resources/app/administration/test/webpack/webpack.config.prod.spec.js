/* eslint-disable */
const path = require('path');

const folderAdministration = path.resolve(__dirname, '..', '..');
const folderStorefront = path.resolve(__dirname, '..', '..', '..', '..', '..', '..', 'Storefront', 'Resources', 'app', 'administration');

describe('check if production webpack config is valid', () => {
    process.env.mode = 'production';

    const webpackConfigProdOld = require('src/../build/webpack.prod.conf.js');
    const webpackConfigProdNew = require('src/../webpack.config.js');

    [
        webpackConfigProdOld,
        webpackConfigProdNew
    ].forEach(webpackConfig => {
        it('should contain the correct mode', () => {
            expect(webpackConfig.mode).toEqual('production');
        });

        it('should contain the correct performance options', () => {
            expect(webpackConfig.performance).toEqual({ hints: false });
        });

        it('should contain the correct optimization options', () => {
            expect(webpackConfig.optimization.moduleIds).toEqual('hashed');
            expect(webpackConfig.optimization.chunkIds).toEqual('named');
            expect(webpackConfig.optimization.runtimeChunk).toEqual({ name: 'runtime' });
            expect(webpackConfig.optimization.splitChunks).toEqual({
                cacheGroups: {
                    'runtime-vendor': {
                        chunks: 'all',
                        name: 'vendors-node',
                        test: path.resolve(folderAdministration, 'node_modules')
                    }
                },
                minSize: 0
            });

            expect(webpackConfig.optimization).toHaveProperty('minimizer');

            const terserPlugin = webpackConfig.optimization.minimizer.find((value) => {
                return value.constructor.name === 'TerserPlugin';
            });
            const optimizeCssAssetsWebpackPlugin = webpackConfig.optimization.minimizer.find((value) => {
                return value.constructor.name === 'OptimizeCssAssetsWebpackPlugin';
            });

            expect(terserPlugin).not.toBeUndefined();
            expect(optimizeCssAssetsWebpackPlugin).not.toBeUndefined();

            expect(terserPlugin.options.terserOptions.warnings).toEqual(false);
            expect(terserPlugin.options.terserOptions.output).toEqual(6);
            expect(terserPlugin.options.cache).toEqual(true);
            expect(terserPlugin.options.parallel).toEqual(true);
            expect(terserPlugin.options.sourceMap).toEqual(false);
        });

        it('should contain the correct entries', () => {
            expect(webpackConfig.entry).toEqual({
                commons: [
                    path.resolve(folderAdministration, 'src/core/shopware.js')
                ],
                app: path.resolve(folderAdministration, 'src/app/main.js'),
                storefront: path.resolve(folderStorefront, 'src/main.js')
            });
        });

        it('should contain the correct outputs', () => {
            expect(webpackConfig.output).toEqual({
                path: path.resolve(folderAdministration, '..', '..', 'public'),
                filename: 'static/js/[name].js',
                chunkFilename: 'static/js/[name].js',
                publicPath: '/',
                globalObject: 'this'
            });
        });

        it('should contain the correct resolver', () => {
            expect(webpackConfig.resolve).toEqual({
                extensions: [ '.js', '.vue', '.json', '.less', '.twig' ],
                alias: {
                    vue$: 'vue/dist/vue.esm.js',
                    src: path.resolve(folderAdministration, 'src'),
                    module: path.resolve(folderAdministration, 'src/module'),
                    scss: path.resolve(folderAdministration, 'src/app/assets/scss'),
                    assets: path.resolve(folderAdministration, 'static')
                }
            });
        });

        it('should contain the correct modules', () => {
            expect(webpackConfig.module).toHaveProperty('rules');

            const eslintLoader = webpackConfig.module.rules.find(m => m.loader === 'eslint-loader');
            expect(eslintLoader.loader).toEqual('eslint-loader');
            expect(eslintLoader.exclude).toEqual(/node_modules/);
            expect(eslintLoader.enforce).toEqual('pre');
            expect(eslintLoader.include).toEqual([
                path.resolve(folderAdministration, 'src'),
                path.resolve(folderAdministration, 'test'),
                path.resolve(folderStorefront, 'src', 'main.js')
            ]);
            expect(eslintLoader.options.configFile).toEqual(path.resolve(folderAdministration, '.eslintrc.js'));
            expect(eslintLoader.options).hasOwnProperty('formatter');
            expect(eslintLoader.test).toEqual(/\.(js|tsx?|vue)$/);

            const htmlLoader = webpackConfig.module.rules.find(m => m.loader === 'html-loader');
            expect(htmlLoader.test).toEqual(/\.(html|twig)$/);

            const babelLoader = webpackConfig.module.rules.find(m => m.loader === 'babel-loader');
            expect(babelLoader.test).toEqual(/\.(js|tsx?|vue)$/);

            const urlLoader = webpackConfig.module.rules.find(m => m.loader === 'url-loader');
            expect(urlLoader.test).toEqual(/\.(png|jpe?g|gif|svg)(\?.*)?$/);

            const svgInlineLoader = webpackConfig.module.rules.find(m => m.loader === 'svg-inline-loader');
            expect(svgInlineLoader.test).toEqual(/\.svg$/);

            const workerLoader = webpackConfig.module.rules.find(m => m.use && m.use.loader === 'worker-loader');
            expect(workerLoader.test).toEqual(/\.worker\.(js|tsx?|vue)$/);
        });

        it('should contain the correct node', () => {
            expect(webpackConfig.node).toBeUndefined();
        });

        it('should contain the correct devtool', () => {
            expect(webpackConfig.devtool).toEqual('#source-map');
        });

        it('should contain the correct plugins', () => {
            const pluginNames = webpackConfig.plugins.map(plugin => plugin.constructor.name);

            expect(pluginNames).toContain('DefinePlugin');
            expect(pluginNames).toContain('MiniCssExtractPlugin');
            expect(pluginNames).not.toContain('HotModuleReplacementPlugin');
            expect(pluginNames).not.toContain('NoEmitOnErrorsPlugin');
            expect(pluginNames).not.toContain('HtmlWebpackPlugin');
            expect(pluginNames).not.toContain('FriendlyErrorsWebpackPlugin');
            expect(pluginNames).not.toContain('AssetsWebpackPlugin');
            expect(pluginNames).toContain('DefinePlugin');
            expect(pluginNames).toContain('CopyPlugin');
        });

        it('should contain the correct plugins config: DefinePlugin', () => {
            const DefinePlugin = webpackConfig.plugins.find(plugin => plugin.constructor.name === 'DefinePlugin');

            expect(DefinePlugin).toEqual({
                definitions: { 'process.env': { NODE_ENV: '"production"' } }
            })
        });

        it('should contain the correct plugins config: MiniCssExtractPlugin', () => {
            const MiniCssExtractPlugin = webpackConfig.plugins.find(plugin => plugin.constructor.name === 'MiniCssExtractPlugin');

            expect(MiniCssExtractPlugin.options).toHaveProperty('filename');
            expect(MiniCssExtractPlugin.options.filename).toEqual('static/css/[name].css');
        });

        it('should contain the correct plugins config: CopyPlugin', () => {
            const CopyPlugin = webpackConfig.plugins.find(plugin => plugin.constructor.name === 'CopyPlugin');

            expect(CopyPlugin.patterns).toEqual([
                {
                    from: path.resolve(folderAdministration, 'static'),
                    ignore: ['.*'],
                    to: 'static'
                }]);
        });
    });
});
