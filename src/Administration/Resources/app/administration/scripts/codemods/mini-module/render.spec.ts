import type { ComponentApi } from './extract-component-api';
import { renderGlobalTypes, renderIndex, renderPrivate, renderRegister, type Target } from './render';

const api: ComponentApi = {
    props: ['title'],
    emits: ['close'],
    slots: ['default'],
    blocks: ['sw_card'],
    computed: ['label'],
    methods: ['save'],
    unresolved: [],
};

describe('render', () => {
    it('writes the entry files of a registered component', () => {
        // ARRANGE
        const target: Target = { name: 'sw-card', packageName: 'framework', shape: { kind: 'register' } };

        // ACT
        const files = [
            renderIndex(target, api),
            renderRegister(target),
            renderGlobalTypes(target),
            renderPrivate(target),
        ];

        // ASSERT
        expect(files[0]).toContain("name: 'sw-card',\n    load: () => import('./src/sw-card'),");
        expect(files[0]).toContain("methods: [\n            'save',\n        ],");
        expect(files[1]).toContain("import Component, * as Module from './index';");
        expect(files[1]).toContain(
            "Shopware.Component.register(Component);\nShopware._private.publish('components', 'sw-card', Module);",
        );
        expect(files[2]).toContain("'sw-card': typeof Component;");
        expect(files[3]).toBeNull();
    });

    it('writes the entry files of a component that extends another one', () => {
        // ARRANGE
        const target: Target = {
            name: 'sw-media-collapse',
            packageName: 'discovery',
            shape: { kind: 'extend', base: 'sw-collapse' },
        };

        // ACT
        const files = [
            renderIndex(target, api),
            renderRegister(target),
            renderGlobalTypes(target),
            renderPrivate(target),
        ];

        // ASSERT
        expect(files[0]).toContain(
            "name: 'sw-media-collapse',\n    extends: 'sw-collapse',\n    load: () => import('./src/sw-media-collapse'),",
        );
        expect(files[0]).toContain('and what it inherits from sw-collapse,');
        expect(files[1]).toContain(
            "Shopware.Component.extend(Component);\nShopware._private.publish('components', 'sw-media-collapse', Module);",
        );
        expect(files[2]).toContain("'sw-media-collapse': typeof Component;");
        expect(files[3]).toBeNull();
    });

    it('writes the entry files of a component that its parent imports, without a global name', () => {
        // ARRANGE
        const target: Target = { name: 'sw-card', packageName: 'framework', shape: { kind: 'local' } };

        // ACT
        const files = [
            renderIndex(target, api),
            renderRegister(target),
            renderGlobalTypes(target),
            renderPrivate(target),
        ];

        // ASSERT
        expect(files[0]).toContain("props: [\n            'title',\n        ],");
        expect(files[0]).not.toContain('methods:');
        expect(files[0]).not.toContain('blocks:');
        expect(files[1]).toBe(`import * as Module from './index';

/**
 * @sw-package framework
 */

Shopware._private.publish('components', 'sw-card', Module);
`);
        expect(files[2]).toBeNull();
        expect(files[3]).toContain("/** @private */\nexport { default } from './src/sw-card';");
    });
});
