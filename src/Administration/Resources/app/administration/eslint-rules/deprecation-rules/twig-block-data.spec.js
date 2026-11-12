/**
 * @sw-package framework
 *
 * Checks the data of no-removed-twig-blocks and no-renamed-twig-blocks against the core templates. In 6.7 they still
 * render every block that 6.8 removes or renames, so a typo in the data or a block removed too early fails here.
 * Skipped from 6.8 on, where the removed blocks are gone.
 */

const fs = require('fs');
const path = require('path');
const { groups } = require('./no-removed-twig-blocks.data');
const renames = require('./no-renamed-twig-blocks.data');

const SOURCE_ROOT = path.resolve(__dirname, '../../../../../..');
const TEMPLATE_ROOTS = [
    path.resolve(__dirname, '../../src'),
    path.join(SOURCE_ROOT, 'Storefront/Resources/app/administration/src'),
];
const SHOPWARE_MAJOR = /SHOPWARE_FALLBACK_VERSION = '(\d+\.\d+)/.exec(
    fs.readFileSync(path.join(SOURCE_ROOT, 'Core/Kernel.php'), 'utf8'),
)?.[1];

const BLOCK = /\{%-?\s*block\s+(\w+)/g;
const EXTEND = /Component\s*\.\s*extend\(\s*(['"`])([\w-]+)\1\s*,\s*(['"`])([\w-]+)\3/g;

/**
 * The blocks of every core template by component, and the base of every core component that extends another one.
 */
function readCore() {
    const blocks = new Map();
    const bases = new Map();

    const visit = (directory) => {
        fs.readdirSync(directory, { withFileTypes: true }).forEach((entry) => {
            const file = path.join(directory, entry.name);

            if (entry.isDirectory()) {
                visit(file);
            } else if (entry.name.endsWith('.html.twig')) {
                const component = entry.name.replace(/\.html\.twig$/, '');
                const names = [...fs.readFileSync(file, 'utf8').matchAll(BLOCK)].map((match) => match[1]);

                blocks.set(component, new Set([...(blocks.get(component) ?? []), ...names]));
            } else if (/\.[jt]s$/.test(entry.name) && !entry.name.includes('.spec.')) {
                [...fs.readFileSync(file, 'utf8').matchAll(EXTEND)].forEach((match) => bases.set(match[2], match[4]));
            }
        });
    };

    TEMPLATE_ROOTS.forEach(visit);

    return { blocks, bases };
}

(SHOPWARE_MAJOR === '6.7' ? describe : describe.skip)('6.8 Twig block data against the 6.7 core templates', () => {
    const { blocks, bases } = readCore();

    /** Whether the component's template or the template of a component it extends defines the block. */
    const rendersBlock = (component, name) => {
        for (let current = component; current; current = bases.get(current)) {
            if (blocks.get(current)?.has(name)) {
                return true;
            }
        }

        return false;
    };

    const extendsComponent = (component, base) => {
        for (let current = bases.get(component); current; current = bases.get(current)) {
            if (current === base) {
                return true;
            }
        }

        return false;
    };

    it('lists only blocks that the core component renders as removed blocks', () => {
        const missing = groups.flatMap((group) => {
            return Object.keys(group.blocks)
                .filter((name) => !rendersBlock(group.component, name))
                .map((name) => `${group.component}: ${name}`);
        });

        expect(missing).toEqual([]);
    });

    it('lists only components that extend the core component as inheriting its removed blocks', () => {
        const wrong = groups.flatMap((group) => {
            return group.inherited
                .filter((component) => !extendsComponent(component, group.component))
                .map((component) => `${component} does not extend ${group.component}`);
        });

        expect(wrong).toEqual([]);
    });

    it('lists only renamed blocks and 6.8 targets that the core components render', () => {
        const missing = renames.flatMap((rename) => {
            const target = rename.to ?? rename.use;
            const targetComponent = rename.in ?? rename.component;

            return [
                ...(rendersBlock(rename.component, rename.from) ? [] : [`${rename.component}: ${rename.from}`]),
                ...(!target || rendersBlock(targetComponent, target) ? [] : [`${targetComponent}: ${target}`]),
            ];
        });

        expect(missing).toEqual([]);
    });
});
