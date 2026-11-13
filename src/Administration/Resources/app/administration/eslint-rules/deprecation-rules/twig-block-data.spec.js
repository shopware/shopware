/**
 * @sw-package framework
 *
 * Checks the data of no-removed-twig-blocks and no-renamed-twig-blocks against the core templates, in both directions.
 * In 6.7 they still render every block that 6.8 removes or renames, so a typo in the data or a block removed too early
 * fails here. So does a block that a template marks with `@deprecated tag:v6.8.0` or only renders without the
 * `v6.8.0.0` flag, when the data misses it. Skipped from 6.8 on, where the removed blocks are gone.
 */

const fs = require('fs');
const path = require('path');
const vueParser = require('vue-eslint-parser');
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
const MARKER = '@deprecated tag:v6.8.0';
const FLAG = /feature\.isActive\(\s*['"]v6\.8\.0\.0['"]\s*\)/;
const OPEN = '<template>';

/**
 * The blocks right below a `@deprecated tag:v6.8.0` marker. A marker on the root block deprecates the whole
 * component, which the component codemods migrate, so the root block is left out.
 */
function markedBlocks(text) {
    const lines = text.split('\n');
    const root = /\{%-?\s*block\s+(\w+)/.exec(text)?.[1];
    const marked = [];

    lines.forEach((line, index) => {
        const marker = line.indexOf(MARKER);

        if (marker === -1) {
            return;
        }

        for (let next = index; next < Math.min(index + 5, lines.length); next += 1) {
            const rest = next === index ? line.slice(marker) : lines[next];
            const block = /\{%-?\s*block\s+(\w+)/.exec(rest)?.[1];

            if (block) {
                if (block !== root) {
                    marked.push(block);
                }

                return;
            }

            if (next > index && /<[a-zA-Z]/.test(rest)) {
                return;
            }
        }
    });

    return marked;
}

/**
 * The blocks that only render without the `v6.8.0.0` flag: inside `v-if="!feature.isActive('v6.8.0.0')"` (also
 * combined with `&&`) or in the `v-else` after `v-if="feature.isActive('v6.8.0.0')"`.
 */
function legacyBlocks(text) {
    // Twig comments may hold markup; blank them out, keeping every offset
    const masked = text.replace(/\{#[\s\S]*?#\}/g, (comment) => comment.replace(/[^\n]/g, ' '));
    const { templateBody } = vueParser.parseForESLint(`${OPEN}${masked}</template>`, {
        filePath: 'template.vue',
        ecmaVersion: 2022,
    }).ast;
    const ranges = [];
    const directive = (element, name) =>
        element.startTag.attributes.find((attribute) => attribute.directive && attribute.key.name.name === name);

    const visit = (element) => {
        const children = element.children.filter((child) => child.type === 'VElement');

        children.forEach((child, index) => {
            const next = children[index + 1];
            const condition = directive(child, 'if') ?? directive(child, 'else-if');
            const code = condition?.value
                ? masked.slice(condition.value.range[0] - OPEN.length + 1, condition.value.range[1] - OPEN.length - 1).trim()
                : '';

            if (!FLAG.test(code)) {
                return;
            }

            if (/(^|&&\s*)!\s*feature\.isActive\([^)]*\)(\s*&&|$)/.test(code)) {
                ranges.push(child.range);
            } else if (/^feature\.isActive\([^)]*\)$/.test(code) && next && directive(next, 'else')) {
                ranges.push(next.range);
            }
        });

        children.forEach(visit);
    };

    visit(templateBody);

    const inLegacy = (offset) => ranges.some(([start, end]) => start <= offset + OPEN.length && offset + OPEN.length < end);
    const matches = [...masked.matchAll(BLOCK)];
    const elsewhere = new Set(matches.filter((match) => !inLegacy(match.index)).map((match) => match[1]));

    return matches.filter((match) => inLegacy(match.index) && !elsewhere.has(match[1])).map((match) => match[1]);
}

/**
 * Per component: the blocks of every core template, the blocks it marks for 6.8 and the blocks that only render
 * without the `v6.8.0.0` flag. Also the base of every core component that extends another one.
 */
function readCore() {
    const blocks = new Map();
    const marked = new Map();
    const legacy = new Map();
    const bases = new Map();
    const add = (map, component, names) => map.set(component, new Set([...(map.get(component) ?? []), ...names]));

    const visit = (directory) => {
        fs.readdirSync(directory, { withFileTypes: true }).forEach((entry) => {
            const file = path.join(directory, entry.name);

            if (entry.isDirectory()) {
                visit(file);
            } else if (entry.name.endsWith('.html.twig')) {
                const component = entry.name.replace(/\.html\.twig$/, '');
                const text = fs.readFileSync(file, 'utf8');
                const names = [...text.matchAll(BLOCK)].map((match) => match[1]);

                add(blocks, component, names);
                add(marked, component, text.includes(MARKER) ? markedBlocks(text) : []);
                add(legacy, component, FLAG.test(text) ? legacyBlocks(text) : []);
            } else if (/\.[jt]s$/.test(entry.name) && !entry.name.includes('.spec.')) {
                [...fs.readFileSync(file, 'utf8').matchAll(EXTEND)].forEach((match) => bases.set(match[2], match[4]));
            }
        });
    };

    TEMPLATE_ROOTS.forEach(visit);

    return { blocks, marked, legacy, bases };
}

(SHOPWARE_MAJOR === '6.7' ? describe : describe.skip)('6.8 Twig block data against the 6.7 core templates', () => {
    const { blocks, marked, legacy, bases } = readCore();

    const listed = new Set([
        ...groups.flatMap((group) =>
            [group.component, ...group.inherited].flatMap((component) =>
                Object.keys(group.blocks).map((name) => `${component}: ${name}`),
            ),
        ),
        ...renames.map((rename) => `${rename.component}: ${rename.from}`),
    ]);

    /** `<component>: <block>` for every block of the map, and those of them the data misses. */
    const check = (byComponent) => {
        const found = [...byComponent].flatMap(([component, names]) => [...names].map((name) => `${component}: ${name}`));

        return { found, unlisted: found.filter((entry) => !listed.has(entry)) };
    };

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

    it('lists every block that a core template marks with @deprecated tag:v6.8.0', () => {
        const { found, unlisted } = check(marked);

        expect(found.length).toBeGreaterThan(0);
        expect(unlisted).toEqual([]);
    });

    it('lists every block that a core template only renders without the v6.8.0.0 flag', () => {
        const { found, unlisted } = check(legacy);

        expect(found.length).toBeGreaterThan(0);
        expect(unlisted).toEqual([]);
    });
});
