const RulerTester = require('eslint').RuleTester;
const rule = require('./require-explicit-emits');

const typeScriptLanguageOptions = {
    parser: require('@typescript-eslint/parser'),
};

const tester = new RulerTester({
    languageOptions: {
        ecmaVersion: 2015,
        sourceType: 'module',
    },
});

tester.run('require-explicit-emits', rule, {
    valid: [
        {
            name: 'component with emit definition for one emit',
            filename: 'test.js',
            code: `

            export default {
                emits: ['my-awesome-event'],

                methods: {
                    myMethod() {
                        this.$emit('my-awesome-event');
                    }
                }
            }
            `,
        },
        {
            name: 'file that emits without a component definition',
            filename: 'test.js',
            code: `
            export class Emitter {
                notify() {
                    this.$emit('my-awesome-event');
                }
            }
            `,
        },
        {
            name: 'component that declares its emits as an object with validators',
            filename: 'test.js',
            code: `
            export default {
                emits: {
                    save: (payload) => typeof payload === 'string',
                    'update:value': null,
                },

                methods: {
                    myMethod() {
                        this.$emit('save', 'x');
                        this.$emit('update:value', 'x');
                    }
                }
            }
            `,
        },
        {
            name: 'component whose emits come from a variable',
            filename: 'test.js',
            code: `
            export default {
                emits: baseEmits,

                methods: {
                    myMethod() {
                        this.$emit('my-awesome-event');
                    }
                }
            }
            `,
        },
        {
            name: 'TypeScript component that declares its emits with `as const`',
            filename: 'test.ts',
            languageOptions: typeScriptLanguageOptions,
            code: `
            export default Component.wrapComponentConfig({
                emits: ['save'] as const,

                data(): { isLoading: boolean } {
                    return { isLoading: false };
                },

                methods: {
                    onSave(): void {
                        this.$emit('save');
                    },
                },
            });
            `,
        },
    ],
    invalid: [
        {
            name: 'object `emits` field without the emitted event is reported without a fix',
            filename: 'test.js',
            code: `
            export default {
                emits: {
                    save: (payload) => typeof payload === 'string',
                },

                methods: {
                    myMethod() {
                        this.$emit('save', 'x');
                        this.$emit('cancel');
                    }
                }
            }
            `,
            output: null,
            errors: [{
                message: 'Event(s) \'cancel\' not defined in the emits option.',
            }],
        },
        {
            name: 'component wrapped in defineComponent without `emits` field',
            filename: 'test.js',
            code: `
                export default defineComponent({
                    methods: {
                        myMethod() {
                            this.$emit('my-awesome-event');
                        },
                    },
                });
            `,
            output: `
                export default defineComponent({
emits: ['my-awesome-event'],

                    methods: {
                        myMethod() {
                            this.$emit('my-awesome-event');
                        },
                    },
                });
            `,
            errors: [{
                message: 'Event(s) \'my-awesome-event\' not defined in the emits option.',
            }],
        },
        {
            name: '`emits` field inserted after `inject` when a spread follows it',
            filename: 'test.js',
            languageOptions: { ecmaVersion: 2018 },
            code: `
                export default {
                    inject: ['acl'],
                    ...baseOptions,

                    methods: {
                        myMethod() {
                            this.$emit('my-awesome-event');
                        },
                    },
                };
            `,
            output: `
                export default {
                    inject: ['acl'],

emits: ['my-awesome-event'],
                    ...baseOptions,

                    methods: {
                        myMethod() {
                            this.$emit('my-awesome-event');
                        },
                    },
                };
            `,
            errors: [{
                message: 'Event(s) \'my-awesome-event\' not defined in the emits option.',
            }],
        },
        {
            name: 'component without `emits` field',
            filename: 'test.js',
            code: `
                export default {
                    methods: {
                        myMethod() {
                            this.$emit('my-awesome-event');
                        },
                    },
                };
            `,
            output: `
                export default {
emits: ['my-awesome-event'],

                    methods: {
                        myMethod() {
                            this.$emit('my-awesome-event');
                        },
                    },
                };
            `,
            errors: [{
                message: 'Event(s) \'my-awesome-event\' not defined in the emits option.',
            }],
        },
        {
            name: 'component with `emits` field',
            filename: 'test.js',
            code: `
                export default {
                    emits: [],

                    methods: {
                        myMethod() {
                            this.$emit('my-awesome-event');
                        },
                    },
                };
            `,
            output: `
                export default {
                    emits: ['my-awesome-event'],

                    methods: {
                        myMethod() {
                            this.$emit('my-awesome-event');
                        },
                    },
                };
            `,
            errors: [{
                message: 'Event(s) \'my-awesome-event\' not defined in the emits option.',
            }],
        },
        {
            name: 'component without `emits` field and multiple `$emit` calls',
            filename: 'test.js',
            code: `
                export default {
                    methods: {
                        myMethod() {
                            this.$emit('my-awesome-event');
                        },
                        myMethod2() {
                            this.$emit('my-awesome-event-2');
                        },
                        myMethod3() {
                            this.$emit('my-awesome-event-3');
                        },
                    },
                };
            `,
            output: `
                export default {
emits: ['my-awesome-event', 'my-awesome-event-2', 'my-awesome-event-3'],

                    methods: {
                        myMethod() {
                            this.$emit('my-awesome-event');
                        },
                        myMethod2() {
                            this.$emit('my-awesome-event-2');
                        },
                        myMethod3() {
                            this.$emit('my-awesome-event-3');
                        },
                    },
                };
            `,
            errors: [{
                message: 'Event(s) \'my-awesome-event\', \'my-awesome-event-2\', \'my-awesome-event-3\' not defined in the emits option.',
            }],
        },
        {
            name: 'component with some events defined and multiple `$emit` calls with no definition',
            filename: 'test.js',
            code: `
                export default {
                    emits: ['my-awesome-event'],

                    methods: {
                        myMethod() {
                            this.$emit('my-awesome-event');
                        },
                        myMethod2() {
                            this.$emit('my-awesome-event-2');
                        },
                        myMethod3() {
                            this.$emit('my-awesome-event-3');
                        },
                    },
                };
            `,
            output: `
                export default {
                    emits: ['my-awesome-event', 'my-awesome-event-2', 'my-awesome-event-3'],

                    methods: {
                        myMethod() {
                            this.$emit('my-awesome-event');
                        },
                        myMethod2() {
                            this.$emit('my-awesome-event-2');
                        },
                        myMethod3() {
                            this.$emit('my-awesome-event-3');
                        },
                    },
                };
            `,
            errors: [{
                message: 'Event(s) \'my-awesome-event-2\', \'my-awesome-event-3\' not defined in the emits option.',
            }],
        },
        {
            name: '`emits` field inserted after `inject`',
            filename: 'test.js',
            code: `
                export default {
                    inject: ['injectedMethod'],

                    extends: 'parent-component',

                    methods: {
                        myMethod() {
                            this.injectedMethod();
                            this.$emit('my-awesome-event');
                        },
                    },
                };
            `,
            output: `
                export default {
                    inject: ['injectedMethod'],

emits: ['my-awesome-event'],

                    extends: 'parent-component',

                    methods: {
                        myMethod() {
                            this.injectedMethod();
                            this.$emit('my-awesome-event');
                        },
                    },
                };
            `,
            errors: [{
                message: 'Event(s) \'my-awesome-event\' not defined in the emits option.',
            }],
        },
        {
            name: 'TypeScript component without `emits` field',
            filename: 'test.ts',
            languageOptions: typeScriptLanguageOptions,
            code: `
                export default Component.wrapComponentConfig({
                    inject: ['feature'],

                    data(): { isLoading: boolean } {
                        return { isLoading: false };
                    },

                    methods: {
                        onSave(payload: string): void {
                            this.$emit('save', payload as string);
                        },
                    },
                });
            `,
            output: `
                export default Component.wrapComponentConfig({
                    inject: ['feature'],

emits: ['save'],

                    data(): { isLoading: boolean } {
                        return { isLoading: false };
                    },

                    methods: {
                        onSave(payload: string): void {
                            this.$emit('save', payload as string);
                        },
                    },
                });
            `,
            errors: [{
                message: 'Event(s) \'save\' not defined in the emits option.',
            }],
        },
        {
            name: 'TypeScript component with `as const` emits missing an event',
            filename: 'test.ts',
            languageOptions: typeScriptLanguageOptions,
            code: `
                export default Component.wrapComponentConfig({
                    emits: ['save'] as const,

                    methods: {
                        onCancel(): void {
                            this.$emit('save');
                            this.$emit('cancel');
                        },
                    },
                });
            `,
            output: `
                export default Component.wrapComponentConfig({
                    emits: ['save', 'cancel'] as const,

                    methods: {
                        onCancel(): void {
                            this.$emit('save');
                            this.$emit('cancel');
                        },
                    },
                });
            `,
            errors: [{
                message: 'Event(s) \'cancel\' not defined in the emits option.',
            }],
        },
    ],
});
