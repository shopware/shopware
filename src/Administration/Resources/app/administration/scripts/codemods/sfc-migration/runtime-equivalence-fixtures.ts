/**
 * @sw-package framework
 */

/**
 * Small, self-contained source pairs for the SFC migration runtime oracle.
 *
 * These are deliberately strings rather than files: the oracle must never need to write into the
 * Administration or Commercial trees. The Twig input proves the normal conversion path, while the
 * observer template is used only when mounting generated setup code so the test can observe state
 * without depending on a real block registry.
 */

type RuntimeFixture = {
    name: string;
    jsSource: string;
    twigSource: string;
    observerTemplate: string;
};

function twigBlock(name: string, content = '<div />'): string {
    return `{% block ${name} %}${content}{% endblock %}`;
}

const FUNCTION_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-function-shapes',
    jsSource: `
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
    twigSource: twigBlock('sw_runtime_function_shapes'),
    observerTemplate: '<div />',
};

const PARAMETERIZED_DATA_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-data-parameter',
    jsSource: `
        export default {
            data(vm) {
                return { count: vm.initial };
            },
        };
    `,
    twigSource: twigBlock('sw_runtime_data_parameter'),
    observerTemplate: '<div />',
};

const SIBLING_DATA_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-data-sibling',
    jsSource: `
        export default {
            data() {
                return { first: this.second, second: 2 };
            },
        };
    `,
    twigSource: twigBlock('sw_runtime_data_sibling'),
    observerTemplate: '<div />',
};

const DATA_DEPENDENCY_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-data-dependencies',
    jsSource: `
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
    twigSource: twigBlock('sw_runtime_data_dependencies'),
    observerTemplate: '<div />',
};

const PROP_INJECT_DATA_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-data-prop-inject',
    jsSource: `
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
    twigSource: twigBlock('sw_runtime_data_prop_inject'),
    observerTemplate: '<div />',
};

const INJECTION_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-injection',
    jsSource: `
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
    twigSource: twigBlock('sw_runtime_injection'),
    observerTemplate: '<div />',
};

const SAFE_WATCH_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-safe-watches',
    jsSource:
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
    twigSource: twigBlock('sw_runtime_safe_watches'),
    observerTemplate: '<div />',
};

const ROUTE_WATCH_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-route-watches',
    jsSource: `
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
    twigSource: twigBlock('sw_runtime_route_watches'),
    observerTemplate: '<div />',
};

const CLASS_THIS_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-class-this',
    jsSource: `
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
    twigSource: twigBlock('sw_runtime_class_this'),
    observerTemplate: '<div />',
};

const MODULE_IDENTITY_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-module-identity',
    jsSource: `
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
    twigSource: twigBlock('sw_runtime_module_identity'),
    observerTemplate: '<div />',
};

const MODULE_BINDING_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-module-bindings',
    jsSource: `
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
    twigSource: twigBlock('sw_runtime_module_bindings'),
    observerTemplate: '<div />',
};

const CROSS_BLOCK_SIDE_EFFECT_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-cross-block-effects',
    jsSource: `
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
    twigSource: [
        twigBlock('sw_runtime_cross_block_first', '<div v-if="consume()">first</div>'),
        twigBlock('sw_runtime_cross_block_second', '<div v-else>second</div>'),
    ].join('\n'),
    observerTemplate: '<div />',
};

const CREATED_ONCE_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-created-once',
    jsSource: `
        export default {
            created() {
                globalThis.__runtimeEquivalenceProbe.push('created');
            },
        };
    `,
    twigSource: twigBlock('sw_runtime_created_once'),
    observerTemplate: '<div />',
};

const CREATED_ASYNC_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-created-async',
    jsSource: `
        export default {
            async created() {
                await Promise.resolve();
                globalThis.__runtimeEquivalenceProbe.push('created');
            },
        };
    `,
    twigSource: twigBlock('sw_runtime_created_async'),
    observerTemplate: '<div />',
};

const CREATED_EARLY_RETURN_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-created-early-return',
    jsSource: `
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
    twigSource: twigBlock('sw_runtime_created_early_return'),
    observerTemplate: '<div />',
};

const CREATED_LOCAL_COLLISION_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-created-local-collision',
    jsSource: `
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
    twigSource: twigBlock('sw_runtime_created_local_collision'),
    observerTemplate: '<div />',
};

const CREATED_THROW_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-created-throw',
    jsSource: `
        export default {
            created() {
                throw new Error('runtime-equivalence-created-throw');
            },
        };
    `,
    twigSource: twigBlock('sw_runtime_created_throw'),
    observerTemplate: '<div />',
};

const CREATED_REJECT_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-created-reject',
    jsSource: `
        export default {
            async created() {
                await Promise.resolve();
                throw new Error('runtime-equivalence-created-reject');
            },
        };
    `,
    twigSource: twigBlock('sw_runtime_created_reject'),
    observerTemplate: '<div />',
};

const DATA_SCOPE_FIXTURE: RuntimeFixture = {
    name: 'sw-runtime-data-scope',
    jsSource: `
        export default {
            data() {
                return { label: 'scope' };
            },
        };
    `,
    twigSource: twigBlock('sw_runtime_data_scope', '<span />'),
    observerTemplate: '<div />',
};

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
    SIBLING_DATA_FIXTURE,
};
