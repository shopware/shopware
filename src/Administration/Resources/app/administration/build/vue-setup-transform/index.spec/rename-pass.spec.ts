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

    it.each([
        [
            'shorthand without a default expands, keeping the key',
            'const { foo } = config;',
            'const { foo: __swSetupAuthor_foo } = __swSetupAuthor_config;',
        ],
        [
            'shorthand WITH a default expands too, keeping the key and the default',
            'const { foo = 5 } = config;',
            'const { foo: __swSetupAuthor_foo = 5 } = __swSetupAuthor_config;',
        ],
        [
            'renamed key leaves the key alone and aliases the local',
            'const { other: foo } = config;',
            'const { other: __swSetupAuthor_foo } = __swSetupAuthor_config;',
        ],
        [
            'renamed key with a default leaves the key alone',
            'const { other: foo = 5 } = config;',
            'const { other: __swSetupAuthor_foo = 5 } = __swSetupAuthor_config;',
        ],
        [
            'array pattern with a default has no key to protect',
            'const [foo = 5] = config;',
            'const [__swSetupAuthor_foo = 5] = __swSetupAuthor_config;',
        ],
        [
            'rest element aliases the collected name while the sibling shorthand still expands',
            'const { other, ...foo } = config;',
            'const { other: __swSetupAuthor_other, ...__swSetupAuthor_foo } = __swSetupAuthor_config;',
        ],
    ])('renames a destructured binding: %s', (_name, declaration, expected) => {
        const source = stripIndent`
            <script setup>
            const config = { foo: 1, other: 2 };
            ${declaration}
            swDefinePublic({ foo });
            </script>
        `;

        // A destructuring pattern's key and value share one source range in the shorthand forms, so a
        // blind rename rewrites the KEY and the destructure silently reads a property that does not
        // exist - yielding `undefined`, or the default forever when one is present.
        expect(transformOrFail(source, 'sw-destructure.vue').code).toContain(expected);
    });

    it('does not rename class member names that collide with a top-level binding', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            const total = 2;
            class Thing {
                count = 0;
                total() {
                    return 3;
                }
            }
            const thing = new Thing();
            swDefinePublic({ thing });
            </script>
        `;

        const result = transformOrFail(source, 'sw-class-members.vue').code;

        // The class binding itself is renamed, but its member names are its own API: renaming `count`
        // would make `thing.count` read undefined, and renaming `total` would make `thing.total()`
        // throw "is not a function".
        expect(result).toContain(
            'class __swSetupAuthor_Thing {\n    count = 0;\n    total() {\n        return 3;\n    }\n}',
        );
        expect(result).toContain('const __swSetupAuthor_thing = new __swSetupAuthor_Thing();');
    });

    it('does not rename enum member names that collide with a top-level binding', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const active = 1;
            enum Status {
                active,
                done,
            }
            const status = Status.active;
            swDefinePublic({ status });
            </script>
        `;

        const result = transformOrFail(source, 'sw-enum-members.vue').code;

        // `Status` is a runtime binding, so the enum name follows its declaration - but the member
        // `active` is a key on that enum, not a reference to the top-level `active` binding. Renaming
        // it would make `Status.active` undefined.
        expect(result).toContain('enum __swSetupAuthor_Status {\n    active,\n    done,\n}');
        expect(result).toContain('const __swSetupAuthor_status = __swSetupAuthor_Status.active;');
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

    it('does not rewrite meta-property tokens (`import.meta`, `new.target`)', () => {
        const metaSource = stripIndent`
            <script setup>
            const meta = 1;
            const url = import.meta.url;
            swDefinePublic({ meta });
            </script>
        `;
        // `meta` in `import.meta` is a syntax token, not a read of the binding.
        expect(transformOrFail(metaSource, 'sw-meta.vue').code).toContain('const __swSetupAuthor_url = import.meta.url;');

        const targetSource = stripIndent`
            <script setup>
            function make() { return new.target; }
            const target = 5;
            swDefinePublic({ target });
            </script>
        `;
        expect(transformOrFail(targetSource, 'sw-newtarget.vue').code).toContain('return new.target;');
    });

    it('renames a binding used as a JSX tag but leaves intrinsic and member-property tags', () => {
        const source = stripIndent`
            <script setup lang="tsx">
            const Lib = { Btn: () => null };
            const node = <div><Lib.Btn /></div>;
            swDefinePublic({ node });
            </script>
        `;

        const result = transformOrFail(source, 'sw-jsx.tsx').code;

        // The member-expression root `Lib` is a binding and is renamed; `div` (intrinsic) and `.Btn`
        // (member property) are not. Without the fix `<Lib.Btn />` would resolve to the footer binding
        // and fail with a temporal-dead-zone error during setup.
        expect(result).toContain('const __swSetupAuthor_node = <div><__swSetupAuthor_Lib.Btn /></div>;');
    });

    it('renames a name in a sibling block, not just where an inner block shadows it', () => {
        const source = stripIndent`
            <script setup>
            const source = { value: 1 };
            function read() {
                if (globalThis) {
                    const source = { value: 2 };
                    return source.value;
                }
                return source.value;
            }
            const out = read();
            swDefinePublic({ out });
            </script>
        `;

        const result = transformOrFail(source, 'sw-block-shadow.vue').code;

        // The inner-block `const source` shadows only its own block, so the later read outside that block
        // is the top-level binding and must be renamed.
        expect(result).toContain('const source = { value: 2 };');
        expect(result).toContain('return __swSetupAuthor_source.value;');
    });

    it('expands a shorthand type export so the public name survives the rename', () => {
        const source = stripIndent`
            <script setup lang="ts">
            class Thing {}
            export type { Thing };
            const t = new Thing();
            swDefinePublic({ t });
            </script>
        `;

        const result = transformOrFail(source, 'sw-type-export.vue').code;

        // The runtime class is renamed, but the public type export must keep the name `Thing`.
        expect(result).toContain('export type { __swSetupAuthor_Thing as Thing };');
        expect(result).toContain('const __swSetupAuthor_t = new __swSetupAuthor_Thing();');
    });
});
