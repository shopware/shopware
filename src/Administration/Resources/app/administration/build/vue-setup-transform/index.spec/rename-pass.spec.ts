/**
 * @sw-package framework
 */

/**
 * Covers the reference-rename pass that base lowering relies on: every top-level runtime
 * binding is renamed to a reserved `__swSetupAuthor_` alias, and the footer re-declares the original
 * names. These cases pin the edges where a naive rename would corrupt code - shorthand property keys,
 * type-member keys, `typeof` queries, and lexical shadowing.
 */

import { stripIndent, transformOrFail } from './helpers';

describe('build/vue-setup-transform base rename pass', () => {
    it('expands a shorthand property instead of renaming its key', () => {
        const source = stripIndent`
            <script setup>
            import { ref } from 'vue';
            const foo = ref(1);
            const bar = { foo };
            bar['foo'].value = 2;
            swDefinePublic({ foo });
            </script>
        `;

        const result = transformOrFail(source, 'sw-shorthand.vue').code;

        // A blind rename would produce `{ __swSetupAuthor_foo }`, silently renaming the KEY so that
        // bar['foo'] resolves to undefined. The shorthand must expand, keeping the key.
        expect(result).toContain('const __swSetupAuthor_bar = { foo: __swSetupAuthor_foo };');
        expect(result).toContain("__swSetupAuthor_bar['foo'].value = 2;");
    });

    it('leaves type-member keys untouched while renaming a typeof value query', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const count = 1;
            interface Shape {
                count: number;
            }
            type CountType = typeof count;
            const doubled: CountType = count * 2;
            swDefinePublic({ count, doubled });
            </script>
        `;

        const result = transformOrFail(source, 'sw-type-members.vue').code;

        // The interface member key `count` is a type-space name, not a value reference - it must survive.
        expect(result).toContain('interface Shape {\n    count: number;\n}');
        // `typeof count` reads the value binding and must be renamed with it.
        expect(result).toContain('type CountType = typeof __swSetupAuthor_count;');
        expect(result).toContain('const __swSetupAuthor_doubled = __swSetupAuthor_count * 2;');
    });

    it('does not rename a name shadowed by a function parameter', () => {
        const source = stripIndent`
            <script setup>
            const value = 10;
            const clamp = (value) => value > 0 ? value : 0;
            const result = clamp(value);
            swDefinePublic({ result });
            </script>
        `;

        const result = transformOrFail(source, 'sw-shadow-param.vue').code;

        // The arrow parameter `value` shadows the top-level binding, so it stays while the declaration
        // name is aliased.
        expect(result).toContain('const __swSetupAuthor_clamp = (value) => value > 0 ? value : 0;');
        // The outer read at the call site is the renamed top-level binding.
        expect(result).toContain('const __swSetupAuthor_result = __swSetupAuthor_clamp(__swSetupAuthor_value);');
    });

    it('does not rename a name shadowed by a body-local declaration', () => {
        const source = stripIndent`
            <script setup>
            const total = 1;
            function compute() {
                const total = 5;
                return total;
            }
            const out = compute() + total;
            swDefinePublic({ out });
            </script>
        `;

        const result = transformOrFail(source, 'sw-shadow-body.vue').code;

        // The inner `const total` shadows the top-level binding inside the function body, so neither
        // the declaration nor its read is renamed; the enclosing function name is.
        expect(result).toContain('function __swSetupAuthor_compute() {\n    const total = 5;\n    return total;\n}');
        expect(result).toContain('const __swSetupAuthor_out = __swSetupAuthor_compute() + __swSetupAuthor_total;');
    });

    it('renames member-access objects but never the static property name', () => {
        const source = stripIndent`
            <script setup>
            const source = { count: 1 };
            const count = source.count;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'sw-member.vue').code;

        // `source` (the object) is renamed; `.count` (the property name) is not.
        expect(result).toContain('const __swSetupAuthor_count = __swSetupAuthor_source.count;');
    });
});
