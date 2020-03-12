/* eslint-disable */
const path = require('path');

const folderAdministration = path.resolve(__dirname, '..', '..');
const folderStorefront = path.resolve(__dirname, '..', '..', '..', '..', '..', '..', 'Storefront', 'Resources', 'app', 'administration');

describe('check if development webpack config is valid', () => {
    process.env.mode = 'development';

    const webpackConfigDevOld = require('src/../build/webpack.dev.conf');
    const webpackConfigDevNew = require('src/../webpack.config.js');

    [
        webpackConfigDevOld,
        webpackConfigDevNew
    ].forEach(webpackConfigDev => {
        it('should contain the correct performance options', () => {
            expect(webpackConfigDev.performance).toEqual({ hints: false });
        });

        it('should contain the correct optimization options', () => {
            expect(webpackConfigDev.optimization).toEqual({
                moduleIds: 'hashed',
                chunkIds: 'named',
                runtimeChunk: { name: 'runtime' },
                splitChunks: {
                    cacheGroups: {
                        'runtime-vendor': {
                            chunks: 'all',
                            name: 'vendors-node',
                            test: path.resolve(folderAdministration, 'node_modules')
                        }
                    },
                    minSize: 0
                }
            });
        });

        it('should contain the correct entries', () => {
            expect(webpackConfigDev.entry).toEqual({
                commons: [
                    path.resolve(folderAdministration, 'src/core/shopware.js')
                ],
                app: path.resolve(folderAdministration, 'src/app/main.js'),
                storefront: path.resolve(folderStorefront, 'src/main.js')
            });
        });

        it('should contain the correct outputs', () => {
            expect(webpackConfigDev.output).toEqual({
                path: path.resolve(folderAdministration, '..', '..', 'public'),
                filename: 'static/js/[name].js',
                chunkFilename: 'static/js/[name].js',
                publicPath: '/',
                globalObject: 'this'
            });
        });

        it('should contain the correct resolver', () => {
            expect(webpackConfigDev.resolve).toEqual({
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
            expect(webpackConfigDev.module).toHaveProperty('rules');

            const eslintLoader = webpackConfigDev.module.rules.find(m => m.loader === 'eslint-loader');
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

            const htmlLoader = webpackConfigDev.module.rules.find(m => m.loader === 'html-loader');
            expect(htmlLoader.test).toEqual(/\.(html|twig)$/);

            const babelLoader = webpackConfigDev.module.rules.find(m => m.loader === 'babel-loader');
            expect(babelLoader.test).toEqual(/\.(js|tsx?|vue)$/);

            const urlLoader = webpackConfigDev.module.rules.find(m => m.loader === 'url-loader');
            expect(urlLoader.test).toEqual(/\.(png|jpe?g|gif|svg)(\?.*)?$/);

            const svgInlineLoader = webpackConfigDev.module.rules.find(m => m.loader === 'svg-inline-loader');
            expect(svgInlineLoader.test).toEqual(/\.svg$/);

            const workerLoader = webpackConfigDev.module.rules.find(m => m.use && m.use.loader === 'worker-loader');
            expect(workerLoader.test).toEqual(/\.worker\.(js|tsx?|vue)$/);
        });

        it('should contain the correct mode', () => {
            expect(webpackConfigDev.mode).toEqual('development');
        });

        it('should contain the correct node', () => {
            expect(webpackConfigDev.node).toEqual({ __filename: true });
        });

        it('should contain the correct devtool', () => {
            expect(webpackConfigDev.devtool).toEqual('eval-source-map');
        });

        it('should contain the correct plugins', () => {
            const pluginNames = webpackConfigDev.plugins.map(plugin => plugin.constructor.name);

            expect(pluginNames).toContain('DefinePlugin');
            expect(pluginNames).toContain('MiniCssExtractPlugin');
            expect(pluginNames).toContain('HotModuleReplacementPlugin');
            expect(pluginNames).toContain('NoEmitOnErrorsPlugin');
            expect(pluginNames).toContain('HtmlWebpackPlugin');
            expect(pluginNames).toContain('FriendlyErrorsWebpackPlugin');
            expect(pluginNames).toContain('AssetsWebpackPlugin');
        });

        it('should contain the correct plugins config: DefinePlugin', () => {
            const DefinePlugin = webpackConfigDev.plugins.find(plugin => plugin.constructor.name === 'DefinePlugin');

            expect(DefinePlugin).toEqual({
                definitions: { 'process.env': { NODE_ENV: '"development"' } }
            })
        });

        it('should contain the correct plugins config: HotModuleReplacementPlugin', () => {
            const HotModuleReplacementPlugin = webpackConfigDev.plugins.find(plugin => plugin.constructor.name === 'HotModuleReplacementPlugin');

            expect(HotModuleReplacementPlugin).toEqual({
                options: {},
                multiStep: undefined,
                fullBuildTimeout: 200,
                requestTimeout: 10000
            })
        });

        it('should contain the correct plugins config: NoEmitOnErrorsPlugin', () => {
            const NoEmitOnErrorsPlugin = webpackConfigDev.plugins.find(plugin => plugin.constructor.name === 'NoEmitOnErrorsPlugin');

            expect(NoEmitOnErrorsPlugin).toEqual({})
        });

        it('should contain the correct plugins config: HtmlWebpackPlugin', () => {
            const HtmlWebpackPlugin = webpackConfigDev.plugins.find(plugin => plugin.constructor.name === 'HtmlWebpackPlugin');

            expect(HtmlWebpackPlugin.options.template).toEqual('index.html.tpl');
            expect(HtmlWebpackPlugin.options.templateParameters).toHaveProperty('featureFlags');
            expect(HtmlWebpackPlugin.options.templateParameters).toHaveProperty('apiVersion');
            expect(HtmlWebpackPlugin.options.filename).toEqual('index.html');
            expect(HtmlWebpackPlugin.options.hash).toEqual(false);
            expect(HtmlWebpackPlugin.options.inject).toEqual(false);
            expect(HtmlWebpackPlugin.options.compile).toEqual(true);
            expect(HtmlWebpackPlugin.options.favicon).toEqual(false);
            expect(HtmlWebpackPlugin.options.minify).toEqual(false);
            expect(HtmlWebpackPlugin.options.cache).toEqual(true);
            expect(HtmlWebpackPlugin.options.showErrors).toEqual(true);
            expect(HtmlWebpackPlugin.options.chunks).toEqual('all');
            expect(HtmlWebpackPlugin.options.excludeChunks).toEqual([]);
            expect(HtmlWebpackPlugin.options.chunksSortMode).toEqual('auto');
            expect(HtmlWebpackPlugin.options.meta).toEqual({});
            expect(HtmlWebpackPlugin.options.title).toEqual('Webpack App');
            expect(HtmlWebpackPlugin.options.xhtml).toEqual(false);
        });

        it('should contain the correct plugins config: AssetsWebpackPlugin', () => {
            const AssetsWebpackPlugin = webpackConfigDev.plugins.find(plugin => plugin.constructor.name === 'AssetsWebpackPlugin');

            expect(AssetsWebpackPlugin.options.filename).toEqual('sw-plugin-dev.json');
            expect(AssetsWebpackPlugin.options.fileTypes).toEqual(['js', 'css']);
            expect(AssetsWebpackPlugin.options.includeAllFileTypes).toEqual(false);
            expect(AssetsWebpackPlugin.options.fullPath).toEqual(true);
            expect(AssetsWebpackPlugin.options.useCompilerPath).toEqual(true);
            expect(AssetsWebpackPlugin.options.prettyPrint).toEqual(true);
            expect(AssetsWebpackPlugin.options.keepInMemory).toEqual(true);
            expect(typeof AssetsWebpackPlugin.options.processOutput).toEqual('function');
        });
    });
});
