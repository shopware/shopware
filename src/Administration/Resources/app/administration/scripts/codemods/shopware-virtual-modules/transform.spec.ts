/**
 * @sw-package framework
 */

import path from 'node:path';
import { loadRegistry, mixinLocalName, storeLocalName, transformSource } from './transform';
import { transformSfc } from './sfc';
import { bootstrapClosure } from './bootstrap-closure';

const administrationRoot = path.resolve(__dirname, '../../..');
const registry = loadRegistry(administrationRoot);

function run(code: string, fileName = 'component.ts'): ReturnType<typeof transformSource> {
    return transformSource(code, fileName, registry);
}

describe('scripts/codemods/shopware-virtual-modules', () => {
    describe('barrel reads', () => {
        it('rewrites a Shopware.Utils read and imports it from the barrel', () => {
            const result = run("import foo from 'src/foo';\n\nexport const id = Shopware.Utils.createId();\n");

            expect(result.code).toContain("import { createId } from 'shopware:utils';");
            expect(result.code).toContain('export const id = createId();');
        });

        it('rewrites a nested utility namespace at the namespace, not at the leaf', () => {
            const result = run("import foo from 'src/foo';\n\nconst copy = Shopware.Utils.object.cloneDeep(foo);\n");

            expect(result.code).toContain("import { object } from 'shopware:utils';");
            expect(result.code).toContain('const copy = object.cloneDeep(foo);');
        });

        it('rewrites a Shopware.Data class', () => {
            const result = run("import foo from 'src/foo';\n\nconst criteria = new Shopware.Data.Criteria(1, 25);\n");

            expect(result.code).toContain("import { Criteria } from 'shopware:data';");
            expect(result.code).toContain('new Criteria(1, 25)');
        });

        it('leaves a member the barrel does not export alone', () => {
            const result = run("import foo from 'src/foo';\n\nShopware.Utils.notAUtil();\n");

            expect(result.code).not.toContain('shopware:utils');
            expect(result.skips[0].reason).toContain('not an export of shopware:utils');
        });

        it('leaves a bare namespace read alone, since no single export replaces it', () => {
            const result = run("import foo from 'src/foo';\n\nconst utils = Shopware.Utils;\n");

            expect(result.rewrites).toEqual([]);
            expect(result.code).toContain('const utils = Shopware.Utils;');
        });
    });

    describe('registry lookups become subpath imports', () => {
        it('turns a mixin lookup into a default import of its subpath', () => {
            const result = run("import foo from 'src/foo';\n\nconst m = Shopware.Mixin.getByName('sw-form-field');\n");

            expect(result.code).toContain("import swFormFieldMixin from 'shopware:mixins/sw-form-field';");
            expect(result.code).toContain('const m = swFormFieldMixin;');
        });

        it('takes the registry key verbatim, camelCase included', () => {
            const result = run("import foo from 'src/foo';\n\nShopware.Mixin.getByName('ruleContainer');\n");

            expect(result.code).toContain("import ruleContainerMixin from 'shopware:mixins/ruleContainer';");
        });

        it('turns a store lookup into a default import plus a call', () => {
            const result = run("import foo from 'src/foo';\n\nconst s = Shopware.Store.get('notification');\n");

            expect(result.code).toContain("import useNotificationStore from 'shopware:stores/notification';");
            expect(result.code).toContain('const s = useNotificationStore();');
        });

        it('keeps the member access that followed the lookup', () => {
            const result = run("import foo from 'src/foo';\n\nShopware.Store.get('notification').createNotification({});\n");

            expect(result.code).toContain('useNotificationStore().createNotification({});');
        });

        it('leaves a lookup whose key is not a literal alone', () => {
            const result = run("import foo from 'src/foo';\n\nexport const f = (id) => Shopware.Store.get(id);\n");

            expect(result.rewrites).toEqual([]);
            expect(result.skips[0].reason).toBe('store id is not a literal');
        });

        it('leaves a store id that PiniaRootState does not declare alone', () => {
            const result = run("import foo from 'src/foo';\n\nShopware.Store.get('notARegisteredStore');\n");

            expect(result.rewrites).toEqual([]);
            expect(result.skips[0].reason).toContain('not declared in PiniaRootState');
        });
    });

    describe('module-level destructuring', () => {
        it('replaces a barrel destructuring with a named import', () => {
            const result = run("import foo from 'src/foo';\n\nconst { Criteria } = Shopware.Data;\n\nnew Criteria(1, 1);\n");

            expect(result.code).toContain("import { Criteria } from 'shopware:data';");
            expect(result.code).not.toContain('Shopware.Data');
        });

        it('replaces a namespace destructuring with an import from that subpath', () => {
            const result = run("import foo from 'src/foo';\n\nconst { warn } = Shopware.Utils.debug;\n\nwarn('a', 'b');\n");

            expect(result.code).toContain("import { warn } from 'shopware:utils/debug';");
            expect(result.code).not.toContain('Shopware.Utils');
            expect(result.code).toContain("warn('a', 'b');");
        });

        it('imports nothing extra for the branch read the destructuring itself contained', () => {
            const result = run("import foo from 'src/foo';\n\nconst { warn } = Shopware.Utils.debug;\n\nwarn('a');\n");

            expect(result.code).not.toContain("from 'shopware:utils'");
            expect(result.code.match(/^import/gm)).toHaveLength(2);
        });

        it('keeps a renamed binding as an aliased import', () => {
            const result = run(
                "import foo from 'src/foo';\n\nconst { object: objectUtils } = Shopware.Utils;\n\nobjectUtils.cloneDeep(foo);\n",
            );

            expect(result.code).toContain("import { object as objectUtils } from 'shopware:utils';");
        });

        it('keeps a leading comment that belonged to the destructuring', () => {
            const result = run(
                '/**\n * @sw-package framework\n */\n\nconst { types } = Shopware.Utils;\n\nexport const isObject = types.isObject;\n',
            );

            expect(result.code).toContain('@sw-package framework');
            expect(result.code).toContain("import { types } from 'shopware:utils';");
        });

        it('drops a destructuring that owned no comment, rather than leaving an empty statement', () => {
            const result = run(
                "import foo from 'src/foo';\n\nconst { Criteria } = Shopware.Data;\n\nexport const c = new Criteria(1, 1);\n",
            );

            expect(result.code).toContain("import { Criteria } from 'shopware:data';");
            expect(result.code).not.toContain('= Shopware.Data');
            expect(result.code).toContain('export const c = new Criteria(1, 1);');
        });

        it('leaves a nested pattern alone, since no import replaces it', () => {
            const code =
                "import foo from 'src/foo';\n\nconst {\n    object,\n    string: { kebabCase },\n} = Shopware.Utils;\n\nexport { object, kebabCase };\n";

            expect(run(code).code).toBe(code);
        });

        it('leaves a rest element alone, since no import replaces it', () => {
            const code =
                "import foo from 'src/foo';\n\nconst { types, ...rest } = Shopware.Utils;\n\nexport { types, rest };\n";

            expect(run(code).code).toBe(code);
        });

        it('leaves a destructuring inside a function alone, to keep the binding in its own scope', () => {
            const code =
                "import foo from 'src/foo';\n\nexport function f() {\n    const { types } = Shopware.Utils;\n\n    return types;\n}\n";

            expect(run(code).code).toBe(code);
        });
    });

    describe('name collisions', () => {
        it('leaves the read alone when the import name is already imported', () => {
            const result = run(
                "import { Criteria } from 'src/core/data/criteria.data';\n\nnew Shopware.Data.Criteria(1, 1);\n",
            );

            expect(result.rewrites).toEqual([]);
            expect(result.skips[0].reason).toBe('"Criteria" is already bound in this file');
        });

        it('counts a default type import as a binding', () => {
            const code =
                "import type Criteria from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';\n\nnew Shopware.Data.Criteria(1, 1);\n";

            expect(run(code).code).toBe(code);
        });

        it('leaves a destructuring alone when the file binds the same name elsewhere', () => {
            const code =
                "import type Criteria from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';\n\nconst { Criteria } = Shopware.Data;\n\nexport { Criteria };\n";

            expect(run(code).code).toBe(code);
        });

        it('leaves the read alone when a nested binding would shadow the import', () => {
            const code =
                "import foo from 'src/foo';\n\nexport function f() {\n    const types = {};\n\n    return [types, Shopware.Utils.types];\n}\n";

            expect(run(code).code).toBe(code);
        });
    });

    describe('imports', () => {
        it('merges several barrel members into one declaration', () => {
            const result = run(
                "import foo from 'src/foo';\n\nShopware.Utils.createId();\nShopware.Utils.debounce(foo, 1);\n",
            );

            expect(result.code).toContain("import { createId, debounce } from 'shopware:utils';");
        });

        it('gives each subpath its own declaration', () => {
            const result = run(
                "import foo from 'src/foo';\n\nShopware.Store.get('notification');\nShopware.Store.get('system');\n",
            );

            expect(result.code).toContain("import useNotificationStore from 'shopware:stores/notification';");
            expect(result.code).toContain("import useSystemStore from 'shopware:stores/system';");
        });

        it('returns the source untouched when nothing is migratable', () => {
            const code = "import foo from 'src/foo';\n\nShopware.Component.register('x', foo);\n";

            expect(run(code).code).toBe(code);
        });
    });

    describe('one family per run', () => {
        it('leaves the other families alone', () => {
            const code = "import foo from 'src/foo';\n\nShopware.Utils.createId();\nShopware.Store.get('notification');\n";
            const result = transformSource(code, 'component.ts', registry, new Set(['shopware:utils']));

            expect(result.code).toContain("import { createId } from 'shopware:utils';");
            expect(result.code).toContain("Shopware.Store.get('notification');");
        });
    });

    describe('local names', () => {
        it.each([
            [
                'sw-form-field',
                'swFormFieldMixin',
            ],
            [
                'notification',
                'notificationMixin',
            ],
            [
                'ruleContainer',
                'ruleContainerMixin',
            ],
        ])('names the mixin "%s" import "%s"', (key, expected) => {
            expect(mixinLocalName(key)).toBe(expected);
        });

        it.each([
            [
                'notification',
                'useNotificationStore',
            ],
            [
                'swOrderDetail',
                'useSwOrderDetailStore',
            ],
        ])('names the store "%s" import "%s"', (key, expected) => {
            expect(storeLocalName(key)).toBe(expected);
        });
    });

    describe('single-file components', () => {
        it('rewrites inside a script setup block', () => {
            const source = `<template>\n    <div />\n</template>\n\n<script setup lang="ts">\nconst store = Shopware.Store.get('notification');\n</script>\n`;
            const result = transformSfc(source, 'component.vue', (code, fileName) =>
                transformSource(code, fileName, registry),
            );

            expect(result.code).toContain("import useNotificationStore from 'shopware:stores/notification';");
            expect(result.code).toContain('<template>');
        });

        it('leaves the template untouched', () => {
            const source = `<template>\n    <div>{{ Shopware.Utils.createId() }}</div>\n</template>\n\n<script setup lang="ts">\nconst a = 1;\n</script>\n`;
            const result = transformSfc(source, 'component.vue', (code, fileName) =>
                transformSource(code, fileName, registry),
            );

            expect(result.code).toBe(source);
        });
    });

    describe('bootstrap closure', () => {
        const closure = bootstrapClosure(path.join(administrationRoot, 'src'));

        it('reaches the files evaluated before window.Shopware is assigned', () => {
            expect(closure.has(path.join(administrationRoot, 'src/core/shopware.ts'))).toBe(true);
            expect(closure.has(path.join(administrationRoot, 'src/app/adapter/view/vue.adapter.ts'))).toBe(true);
        });

        it('follows an eager import.meta.glob, which Vite compiles into static imports', () => {
            expect(closure.has(path.join(administrationRoot, 'src/app/plugin/sanitize.plugin.js'))).toBe(true);
            expect(closure.has(path.join(administrationRoot, 'src/core/service/api/acl.api.service.js'))).toBe(true);
        });

        it('stops at the dynamic imports that only resolve after boot', () => {
            expect(closure.has(path.join(administrationRoot, 'src/app/main.ts'))).toBe(false);
        });
    });
});
