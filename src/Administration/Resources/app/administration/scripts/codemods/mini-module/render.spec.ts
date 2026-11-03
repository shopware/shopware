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
        const target: Target = { name: 'sw-card', packageName: 'framework', shape: { kind: 'register', isPrivate: false } };

        // ACT
        const files = [
            renderIndex(target, api),
            renderRegister(target),
            renderGlobalTypes(target),
            renderPrivate(target, api),
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
            shape: { kind: 'extend', base: 'sw-collapse', isPrivate: false },
        };

        // ACT
        const files = [
            renderIndex(target, api),
            renderRegister(target),
            renderGlobalTypes(target),
            renderPrivate(target, api),
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

    it('writes only a private.ts for a component that its parent imports, without a global name', () => {
        // ARRANGE
        const target: Target = {
            name: 'sw-card',
            packageName: 'framework',
            shape: { kind: 'nested', parent: 'src/module/sw-x/page/sw-x-index' },
        };

        // ACT
        const files = [
            renderIndex(target, api),
            renderRegister(target),
            renderGlobalTypes(target),
            renderPrivate(target, api),
        ];

        // ASSERT
        expect(files.slice(0, 3)).toEqual([
            null,
            null,
            null,
        ]);
        expect(files[3]).toContain("import { definePrivateComponent } from 'src/core/public-component';");
        expect(files[3]).toContain(
            "/** @private */\nexport default definePrivateComponent({\n    name: 'sw-card',\n    load: () => import('./src/sw-card'),",
        );
        expect(files[3]).toContain("props: [\n            'title',\n        ],");
        expect(files[3]).not.toContain('methods:');
    });

    it('writes a private.ts, register.ts and private global.d.ts for a component registered @private', () => {
        // ARRANGE
        const target: Target = {
            name: 'sw-cms-detail',
            packageName: 'discovery',
            shape: { kind: 'extend', base: 'sw-cms-base', isPrivate: true },
        };

        // ACT
        const files = [
            renderIndex(target, api),
            renderRegister(target),
            renderGlobalTypes(target),
            renderPrivate(target, api),
        ];

        // ASSERT
        expect(files[0]).toBeNull();
        expect(files[1]).toBe(`import Component from './private';

/**
 * @sw-package discovery
 */

Shopware.Component.extend(Component);
`);
        expect(files[2]).toContain("import type Component from './private';");
        expect(files[2]).toContain("interface PrivateComponents {\n        'sw-cms-detail': typeof Component;");
        expect(files[3]).toContain(
            "export default definePrivateComponent({\n    name: 'sw-cms-detail',\n    extends: 'sw-cms-base',\n    load:",
        );
        expect(files[3]).toContain("methods: [\n            'save',\n        ],");
    });
});
