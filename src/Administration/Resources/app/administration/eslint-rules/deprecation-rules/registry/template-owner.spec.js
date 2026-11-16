/**
 * @sw-package framework
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { getTemplateOwner } = require('./template-owner');

describe('registry/template-owner', () => {
    let directory;

    const contextFor = (file) => ({ physicalFilename: path.join(directory, file) });
    const targetsOf = (file) => getTemplateOwner(contextFor(file)).targets;
    const write = (file, content) => {
        fs.mkdirSync(path.dirname(path.join(directory, file)), { recursive: true });
        fs.writeFileSync(path.join(directory, file), content);
    };

    beforeEach(() => {
        directory = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-twig-target-'));
    });

    afterEach(() => {
        fs.rmSync(directory, { recursive: true, force: true });
    });

    it('reads the override target from the script next to the template', () => {
        write('acme/index.js', "import template from './acme.html.twig';\nShopware.Component.override('sw-order-list', { template });");
        write('acme/acme.html.twig', '');

        expect(targetsOf('acme/acme.html.twig')).toEqual(['sw-order-list']);
    });

    it('reads every script of the folder, not only index.js, and ignores specs', () => {
        write('acme/sw-order-list.js', "import template from './sw-order-list.html.twig';\nComponent.override('sw-order-list', { template });");
        write('acme/sw-order-list.spec.js', "Component.override('sw-product-list', {});");

        expect(targetsOf('acme/sw-order-list.html.twig')).toEqual(['sw-order-list']);
    });

    it('uses the base of an extension', () => {
        write('acme/index.ts', "Component.extend('acme-list', 'sw-order-list', {});");

        expect(targetsOf('acme/acme-list.html.twig')).toEqual(['sw-order-list']);
    });

    it('knows the templates of components the extension registers itself', () => {
        write('acme-copy/acme-copy.js', "import template from './acme-copy.html.twig';\nShopware.Component.register('acme-copy', { template });");
        write('acme-copy/index.js', "import './acme-copy';");
        write('acme-element/index.ts', "Shopware.Component.register('sw-cms-el-config-acme', () => import('./config'));");
        write('acme-element/config/index.ts', 'export default { template };');
        write('acme-list/index.js', "Shopware.Component.register('acme-list', {});\nShopware.Component.override('sw-order-list', {});");

        expect(getTemplateOwner(contextFor('acme-copy/acme-copy.html.twig'))).toEqual({ targets: [], ownComponent: true });
        expect(getTemplateOwner(contextFor('acme-element/config/sw-cms-el-config-acme.html.twig')).ownComponent).toBe(true);
        expect(getTemplateOwner(contextFor('acme-list/acme-list.html.twig'))).toEqual({ targets: ['sw-order-list'], ownComponent: false });
    });

    it('falls back to core component file and folder names', () => {
        expect(targetsOf('sw-product-detail/sw-product-detail-base.html.twig')).toEqual([
            'sw-product-detail-base',
            'sw-product-detail',
        ]);
        expect(targetsOf('acme/acme-list.html.twig')).toEqual([]);
    });
});
