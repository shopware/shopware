/**
 * @sw-package framework
 *
 * Vite plugin that compiles SCSS for themes in dev server mode
 * Reads theme-files.json and theme variables to create a virtual CSS entry point
 */
import path from 'node:path';
import fs from 'node:fs';
import chalk from 'chalk';

const VIRTUAL_MODULE_ID = 'virtual:shopware-theme.scss';
const RESOLVED_VIRTUAL_MODULE_ID = '\0' + VIRTUAL_MODULE_ID;

export default function shopwareScssPlugin(options = {}) {
    const {
        projectRootPath,
        enabled = true,
    } = options;

    if (!enabled) {
        // eslint-disable-next-line no-console
        console.log(chalk.yellow('⚠ SCSS compilation disabled via configuration\n'));
        return {
            name: 'shopware-scss',
        };
    }

    const themeFilesPath = path.resolve(projectRootPath, 'var/theme-files.json');
    const configFeaturesPath = path.resolve(projectRootPath, 'var/config_js_features.json');

    let server;
    let themeConfig = null;

    /**
     * Load theme configuration from theme-files.json
     */
    const loadThemeConfig = () => {
        if (!fs.existsSync(themeFilesPath)) {
            throw new Error(
                `Theme files not found at ${themeFilesPath}. ` +
                `Please run 'bin/console theme:compile' first to generate theme configuration.`
            );
        }

        const content = fs.readFileSync(themeFilesPath, 'utf-8');
        return JSON.parse(content);
    };

    /**
     * Load feature flags configuration
     */
    const loadFeatureFlags = () => {
        if (!fs.existsSync(configFeaturesPath)) {
            return {};
        }

        const content = fs.readFileSync(configFeaturesPath, 'utf-8');
        return JSON.parse(content);
    };

    /**
     * Convert feature flags to SCSS map format
     */
    const featureFlagsToScssMap = (features) => {
        const entries = Object.entries(features).map(([key, value]) => {
            const scssValue = value ? 'true' : 'false';
            return `    '${key}': ${scssValue}`;
        });

        return `$sw-features: (\n${entries.join(',\n')}\n);\n`;
    };

    /**
     * Generate the virtual SCSS module content
     */
    const generateScssContent = () => {
        const config = loadThemeConfig();
        const features = loadFeatureFlags();

        const themeId = config.themeId;
        const styleFiles = config.style || [];
        
        if (!themeId) {
            throw new Error('No themeId found in theme-files.json');
        }

        // Build SCSS content
        let scssContent = '// Auto-generated virtual SCSS module for Shopware theme\n\n';

        // 1. Add feature flags
        if (Object.keys(features).length > 0) {
            scssContent += '// Feature flags\n';
            scssContent += featureFlagsToScssMap(features);
            scssContent += '\n';
        }

        // 2. Override asset path variables for dev server
        scssContent += '// Dev server asset path overrides\n';
        scssContent += `$app-css-relative-asset-path: '/theme/${themeId}/assets';\n`;
        scssContent += `$sw-asset-public-url: '';\n`;
        scssContent += `$sw-asset-theme-url: '';\n`;
        scssContent += `$sw-asset-asset-url: '';\n`;
        scssContent += `$sw-asset-sitemap-url: '';\n`;
        scssContent += '\n';

        // 3. Import theme variables
        const themeVariablesPath = path.resolve(projectRootPath, `var/theme-variables/${themeId}.scss`);
        if (fs.existsSync(themeVariablesPath)) {
            scssContent += '// Theme variables\n';
            scssContent += `@import '${themeVariablesPath}';\n`;
            scssContent += '\n';
        } else {
            // eslint-disable-next-line no-console
            console.warn(chalk.yellow(`⚠ Theme variables file not found: ${themeVariablesPath}`));
        }

        // 4. Import all theme SCSS files
        scssContent += '// Theme SCSS files\n';
        for (const styleFile of styleFiles) {
            const filePath = styleFile.filepath;
            if (fs.existsSync(filePath)) {
                scssContent += `@import '${filePath}';\n`;
            } else {
                // eslint-disable-next-line no-console
                console.warn(chalk.yellow(`⚠ SCSS file not found: ${filePath}`));
            }
        }

        return scssContent;
    };

    return {
        name: 'shopware-scss',

        configureServer(_server) {
            server = _server;

            // Pre-load theme config to catch errors early
            try {
                themeConfig = loadThemeConfig();
                // eslint-disable-next-line no-console
                console.log(chalk.green(`✓ Loaded theme configuration: ${themeConfig.technicalName} (${themeConfig.themeId})`));
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(chalk.red('✗ Failed to load theme configuration:'), error.message);
                throw error;
            }

            // Watch theme-files.json and theme variables for changes
            const watcher = server.watcher;
            
            watcher.add(themeFilesPath);
            watcher.add(path.resolve(projectRootPath, 'var/theme-variables/*.scss'));

            watcher.on('change', (file) => {
                if (file === themeFilesPath || file.includes('theme-variables')) {
                    // eslint-disable-next-line no-console
                    console.log(chalk.blue('📝 Theme configuration changed, reloading...'));
                    
                    // Reload theme config
                    themeConfig = loadThemeConfig();
                    
                    // Invalidate the virtual module
                    const module = server.moduleGraph.getModuleById(RESOLVED_VIRTUAL_MODULE_ID);
                    if (module) {
                        server.moduleGraph.invalidateModule(module);
                    }

                    // Trigger full page reload (as requested)
                    server.ws.send({
                        type: 'full-reload',
                        path: '*',
                    });
                }
            });

            // eslint-disable-next-line no-console
            console.log(chalk.green('✓ SCSS compilation enabled for dev server\n'));
        },

        resolveId(id) {
            if (id === VIRTUAL_MODULE_ID) {
                // eslint-disable-next-line no-console
                console.log(chalk.green(`[SCSS Plugin] ✓ Resolved virtual module: ${VIRTUAL_MODULE_ID}`));
                return RESOLVED_VIRTUAL_MODULE_ID;
            }
            return null;
        },

        load(id) {
            if (id === RESOLVED_VIRTUAL_MODULE_ID) {
                // eslint-disable-next-line no-console
                console.log(chalk.blue('[SCSS Plugin] Loading virtual module...'));
                try {
                    const content = generateScssContent();
                    // eslint-disable-next-line no-console
                    console.log(chalk.green(`[SCSS Plugin] ✓ Generated ${content.length} bytes of SCSS`));
                    return content;
                } catch (error) {
                    // eslint-disable-next-line no-console
                    console.error(chalk.red('✗ Error generating SCSS:'), error);
                    throw error;
                }
            }
            return null;
        },

        handleHotUpdate({ file, server }) {
            // If any SCSS file in the theme changes, reload
            if (file.endsWith('.scss')) {
                const config = themeConfig || loadThemeConfig();
                const styleFiles = config.style || [];
                
                // Check if this file is part of the theme
                const isThemeFile = styleFiles.some(sf => sf.filepath === file);
                
                if (isThemeFile) {
                    // eslint-disable-next-line no-console
                    console.log(chalk.blue(`📝 SCSS file changed: ${path.basename(file)}`));
                    
                    // Trigger full page reload
                    server.ws.send({
                        type: 'full-reload',
                        path: '*',
                    });
                    
                    return [];
                }
            }
        },
    };
}

