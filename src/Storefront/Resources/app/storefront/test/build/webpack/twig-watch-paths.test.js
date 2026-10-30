/**
 * @sw-package framework
 */
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const getTwigWatchPaths = require('../../../build/webpack/twig-watch-paths');

describe('build/webpack/twig-watch-paths', () => {
    let projectRoot;

    const pluginDefinition = {
        Framework: { technicalName: 'framework', basePath: 'src/Core/Framework/', views: ['Resources/views'] },
        Storefront: { technicalName: 'storefront', basePath: 'src/Storefront/', views: ['Resources/views'] },
        ThemePlugin: { technicalName: 'theme-plugin', basePath: 'custom/plugins/ThemePlugin/src/', views: ['Resources/views'] },
        TemplateOnly: { technicalName: 'template-only', basePath: 'custom/plugins/TemplateOnly/src/', views: ['Resources/views'] },
        ComponentOnly: { technicalName: 'component-only', basePath: 'custom/plugins/ComponentOnly/src/', views: ['Resources/views'] },
        NoViews: { technicalName: 'no-views', basePath: 'custom/plugins/NoViews/src/' },
    };

    function createDirectory(relativePath) {
        fs.mkdirSync(path.join(projectRoot, relativePath), { recursive: true });
    }

    function watchPath(basePath) {
        return `${path.join(projectRoot, basePath, 'Resources/views')}/**/*.twig`;
    }

    beforeEach(() => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-twig-watch-'));

        createDirectory('src/Core/Framework/Resources/views/documents');
        createDirectory('src/Storefront/Resources/views/storefront');
        createDirectory('custom/plugins/ThemePlugin/src/Resources/views/storefront');
        createDirectory('custom/plugins/TemplateOnly/src/Resources/views/storefront');
        createDirectory('custom/plugins/ComponentOnly/src/Resources/views/components');
    });

    afterEach(() => {
        fs.rmSync(projectRoot, { recursive: true, force: true });
    });

    it('watches the Storefront views and all extension views with Storefront templates', () => {
        expect(getTwigWatchPaths(projectRoot, pluginDefinition)).toEqual([
            watchPath('src/Storefront/'),
            watchPath('custom/plugins/ThemePlugin/src/'),
            watchPath('custom/plugins/TemplateOnly/src/'),
            watchPath('custom/plugins/ComponentOnly/src/'),
        ]);
    });

    it('watches only the Storefront views when extensions are skipped', () => {
        expect(getTwigWatchPaths(projectRoot, pluginDefinition, false)).toEqual([
            watchPath('src/Storefront/'),
        ]);
    });
});
