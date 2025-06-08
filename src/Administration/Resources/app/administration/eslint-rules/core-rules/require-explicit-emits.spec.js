const RulerTester = require('eslint').RuleTester;
const rule = require('./require-explicit-emits');

const tester = new RulerTester({
    parserOptions: {
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
    ],
    invalid: [
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
    ],
});
