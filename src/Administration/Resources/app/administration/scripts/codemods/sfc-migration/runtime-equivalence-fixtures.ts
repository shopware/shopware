/**
 * @sw-package framework
 */

/**
 * Small, self-contained source pairs for the SFC migration runtime oracle.
 *
 * These are deliberately strings rather than files: the oracle must never need to write into the
 * Administration or Commercial trees.
 */

type RuntimeFixture = {
    name: string;
    jsSource: string;
    twigSource: string;
};

function twigBlock(name: string, content = '<div />'): string {
    return `{% block ${name} %}${content}{% endblock %}`;
}

/** By default a fixture's Twig is one block named after the component, holding an empty div. */
function runtimeFixture(name: string, jsSource: string, twigSource = twigBlock(name.replace(/-/g, '_'))): RuntimeFixture {
    return { name, jsSource, twigSource };
}

const FUNCTION_FIXTURE = runtimeFixture(
    'sw-runtime-function-shapes',
    `
        export default {
            data() {
                return { asyncValue: null };
            },
            methods: {
                argumentsMethod(value) {
                    return arguments[0] + value;
                },
                recursive: function recursive(value) {
                    return value <= 1 ? 1 : value * recursive(value - 1);
                },
                conciseObject: () => ({ color: 'red' }),
                async load(value) {
                    this.asyncValue = await Promise.resolve(value);
                    return this.asyncValue;
                },
                *generator(value) {
                    yield value * 2;
                },
            },
        };
    `,
);

const PARAMETERIZED_DATA_FIXTURE = runtimeFixture(
    'sw-runtime-data-parameter',
    `
        export default {
            data(vm) {
                return { count: vm.initial };
            },
        };
    `,
);

const SIBLING_DATA_FIXTURE = runtimeFixture(
    'sw-runtime-data-sibling',
    `
        export default {
            data() {
                return { first: this.second, second: 2 };
            },
        };
    `,
);

const DATA_DEPENDENCY_FIXTURE = runtimeFixture(
    'sw-runtime-data-dependencies',
    `
        export default {
            props: ['seed'],
            inject: ['service'],
            data() {
                return {
                    fromComputed: this.double,
                    fromRef: this.$refs.input,
                    fromProp: this.seed,
                    fromInject: this.service,
                };
            },
            computed: {
                double() {
                    return 2;
                },
            },
        };
    `,
);

const PROP_INJECT_DATA_FIXTURE = runtimeFixture(
    'sw-runtime-data-prop-inject',
    `
        export default {
            props: ['seed'],
            inject: ['service'],
            data() {
                return {
                    fromProp: this.seed,
                    fromInject: this.service,
                };
            },
        };
    `,
);

const INJECTION_FIXTURE = runtimeFixture(
    'sw-runtime-injection',
    `
        export default {
            inject: ['provided'],
            methods: {
                read() {
                    return this.provided;
                },
                write(value) {
                    this.provided = value;
                },
            },
        };
    `,
);

const SAFE_WATCH_FIXTURE = runtimeFixture(
    'sw-runtime-safe-watches',
    `
        export default {
            props: ['foo-bar'],
            data() {
                return { nested: { value: 0 }, log: [] };
            },
            watch: {
                'foo-bar'(value) {
                    this.log.push(` +
        '`hyphen:${value}`' +
        `);
                },
                'nested.value'(value) {
                    this.log.push(` +
        '`nested:${value}`' +
        `);
                },
            },
        };
    `,
);

const ROUTE_WATCH_FIXTURE = runtimeFixture(
    'sw-runtime-route-watches',
    `
        export default {
            data() {
                return { log: [] };
            },
            watch: {
                '$route.name'() {
                    this.log.push('name');
                },
                '$route'() {
                    this.log.push('route');
                },
            },
        };
    `,
);

const SHORTCUT_FIXTURE = runtimeFixture(
    'sw-runtime-shortcut',
    `
        export default {
            data() {
                return { enabled: false };
            },
            shortcuts: {
                ESCAPE: { active() { return this.enabled; }, method: 'onEsc' },
                F: 'onFocus',
            },
            methods: {
                onEsc() {
                    globalThis.__runtimeEquivalenceProbe.push('esc');
                },
                onFocus() {
                    globalThis.__runtimeEquivalenceProbe.push('focus');
                },
            },
        };
    `,
);

const CLASS_THIS_FIXTURE = runtimeFixture(
    'sw-runtime-class-this',
    `
        export default {
            data() {
                return { count: 7 };
            },
            methods: {
                readClassField() {
                    class Local {
                        static key = 'read';
                        field = this.count;
                        #private = this.count;

                        [Local.key]() {
                            return this.#private;
                        }
                    }

                    return new Local().field;
                },
            },
        };
    `,
);

const MODULE_IDENTITY_FIXTURE = runtimeFixture(
    'sw-runtime-module-identity',
    `
        const shared = {};

        export default {
            created() {
                globalThis.__runtimeEquivalenceProbe.push(shared);
            },
            methods: {
                getShared() {
                    return shared;
                },
            },
        };
    `,
);

const MODULE_BINDING_FIXTURE = runtimeFixture(
    'sw-runtime-module-bindings',
    `
        const pattern = /shared/;
        let live = 0;
        const holder = {
            get value() {
                return live++;
            },
        };
        const { missing = 42 } = {};

        export default {
            methods: {
                readModule() {
                    return { pattern, getter: holder.value, missing };
                },
            },
        };
    `,
);

const CROSS_BLOCK_SIDE_EFFECT_FIXTURE = runtimeFixture(
    'sw-runtime-cross-block-effects',
    `
        export default {
            data() {
                return { calls: 0 };
            },
            methods: {
                consume() {
                    this.calls += 1;
                    return false;
                },
            },
        };
    `,
    [
        twigBlock('sw_runtime_cross_block_first', '<div v-if="consume()">first</div>'),
        twigBlock('sw_runtime_cross_block_second', '<div v-else>second</div>'),
    ].join('\n'),
);

const CREATED_ONCE_FIXTURE = runtimeFixture(
    'sw-runtime-created-once',
    `
        export default {
            created() {
                globalThis.__runtimeEquivalenceProbe.push('created');
            },
        };
    `,
);

const CREATED_ASYNC_FIXTURE = runtimeFixture(
    'sw-runtime-created-async',
    `
        export default {
            async created() {
                await Promise.resolve();
                globalThis.__runtimeEquivalenceProbe.push('created');
            },
        };
    `,
);

const CREATED_EARLY_RETURN_FIXTURE = runtimeFixture(
    'sw-runtime-created-early-return',
    `
        export default {
            props: ['skip'],
            created() {
                if (this.skip) {
                    return;
                }

                globalThis.__runtimeEquivalenceProbe.push('created');
            },
        };
    `,
);

const CREATED_LOCAL_COLLISION_FIXTURE = runtimeFixture(
    'sw-runtime-created-local-collision',
    `
        export default {
            data() {
                return { ready: false };
            },
            created() {
                const ready = false;
                if (!ready) {
                    this.ready = true;
                }
            },
        };
    `,
);

const CREATED_THROW_FIXTURE = runtimeFixture(
    'sw-runtime-created-throw',
    `
        export default {
            created() {
                throw new Error('runtime-equivalence-created-throw');
            },
        };
    `,
);

const CREATED_REJECT_FIXTURE = runtimeFixture(
    'sw-runtime-created-reject',
    `
        export default {
            async created() {
                await Promise.resolve();
                throw new Error('runtime-equivalence-created-reject');
            },
        };
    `,
);

const DATA_SCOPE_FIXTURE = runtimeFixture(
    'sw-runtime-data-scope',
    `
        export default {
            data() {
                return { label: 'scope' };
            },
        };
    `,
    twigBlock('sw_runtime_data_scope', '<span />'),
);

export {
    type RuntimeFixture,
    CLASS_THIS_FIXTURE,
    CREATED_ASYNC_FIXTURE,
    CREATED_EARLY_RETURN_FIXTURE,
    CREATED_LOCAL_COLLISION_FIXTURE,
    CREATED_ONCE_FIXTURE,
    CREATED_REJECT_FIXTURE,
    CREATED_THROW_FIXTURE,
    CROSS_BLOCK_SIDE_EFFECT_FIXTURE,
    DATA_DEPENDENCY_FIXTURE,
    DATA_SCOPE_FIXTURE,
    FUNCTION_FIXTURE,
    INJECTION_FIXTURE,
    MODULE_IDENTITY_FIXTURE,
    MODULE_BINDING_FIXTURE,
    PARAMETERIZED_DATA_FIXTURE,
    PROP_INJECT_DATA_FIXTURE,
    ROUTE_WATCH_FIXTURE,
    SAFE_WATCH_FIXTURE,
    SHORTCUT_FIXTURE,
    SIBLING_DATA_FIXTURE,
};
