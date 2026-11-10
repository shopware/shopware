/**
 * @sw-package framework
 *
 * Covers twig-blocks.js and twig-target.js.
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { findBlocks } = require('./twig-blocks');
const { twigTargets } = require('./twig-target');

describe('twig-blocks', () => {
    it('finds blocks with the range of their name, also with extra whitespace and trim markers', () => {
        const text = '{% block sw_a %}\n{%  block sw_b %}{% endblock %}\n{%- block sw_c -%}{% endblock sw_c %}{% endblock %}';
        const blocks = findBlocks(text);

        expect(blocks.map(({ name }) => name)).toEqual(['sw_a', 'sw_b', 'sw_c']);
        blocks.forEach(({ name, nameRange }) => expect(text.slice(...nameRange)).toBe(name));
    });
});

describe('twig-target', () => {
    let directory;

    const contextFor = (file) => ({ physicalFilename: path.join(directory, file) });
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

        expect(twigTargets(contextFor('acme/acme.html.twig'))).toEqual(['sw-order-list']);
    });

    it('reads every script of the folder, not only index.js, and ignores specs', () => {
        write('acme/sw-order-list.js', "import template from './sw-order-list.html.twig';\nComponent.override('sw-order-list', { template });");
        write('acme/sw-order-list.spec.js', "Component.override('sw-product-list', {});");

        expect(twigTargets(contextFor('acme/sw-order-list.html.twig'))).toEqual(['sw-order-list']);
    });

    it('uses the base of an extension', () => {
        write('acme/index.ts', "Component.extend('acme-list', 'sw-order-list', {});");

        expect(twigTargets(contextFor('acme/acme-list.html.twig'))).toEqual(['sw-order-list']);
    });

    it('falls back to core component file and folder names', () => {
        expect(twigTargets(contextFor('sw-product-detail/sw-product-detail-base.html.twig'))).toEqual([
            'sw-product-detail-base',
            'sw-product-detail',
        ]);
        expect(twigTargets(contextFor('acme/acme-list.html.twig'))).toEqual([]);
    });
});
