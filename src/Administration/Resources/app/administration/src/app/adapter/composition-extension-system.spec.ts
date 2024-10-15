/**
 * @package admin
 *
 * This test is written in TS to make sure that the type inheritance
 * works correctly with the new Composition API extension system.
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-assignment, max-len, @typescript-eslint/no-unsafe-call, filename-rules/match */

import { createExtendableSetup, overrideComponentSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import { mount } from '@vue/test-utils';
import type { EmitFn, PropType } from 'vue';
import { ref, computed, reactive, defineComponent } from 'vue';
import type { SetupContext, Slot } from '@vue/runtime-core';
import ExampleExtendableScriptSetupComponent from './_mocks_/example-extendable-script-setup-component.vue';

// Helper functions to test type safety, based on https://github.com/tsdjs/tsd
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const expectType = <T>(expression: T) => {};

describe('src/app/adapter/composition-extension-system', () => {
    beforeEach(() => {
        // Reset the overrides map before each test
        const entries = [...Object.keys(_overridesMap)];

        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        // Clear all mocks
        jest.clearAllMocks();
    });

    describe('Refs:', () => {
        describe('Single override:', () => {
            it('should be able to override ref values', async () => {
                const originalComponent = defineComponent({
                    template: '<div>Count: {{ count }}</div>',
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);

                                return {
                                    public: {
                                        count,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.text()).toBe('Count: 1');

                // Override the setup function
                overrideComponentSetup()('originalComponent', () => {
                    return {
                        count: ref(5),
                    };
                });

                await flushPromises();

                expect(wrapper.text()).toBe('Count: 5');
            });

            it('should be able to override ref values and access previous ones', async () => {
                const originalComponent = defineComponent({
                    template: '<div>Count: {{ count }}</div>',
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);

                                return {
                                    public: {
                                        count,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.text()).toBe('Count: 1');

                // Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCount = previousState.count;
                    const newCount = ref(oldCount.value + 5);

                    return {
                        count: newCount,
                    };
                });

                await flushPromises();

                expect(wrapper.text()).toBe('Count: 6');
            });

            it('should be able to override ref values, access previous ones and modify previous ones', async () => {
                const originalComponent = defineComponent({
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const increment = () => {
                                    count.value += 1;
                                };

                                return {
                                    public: {
                                        count,
                                        increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');

                // Change the count
                await wrapper.find('button').trigger('click');
                expect(wrapper.find('.count').text()).toBe('Count: 2');

                // Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCount = previousState.count;
                    const newCount = ref(oldCount.value + 5);

                    return {
                        count: newCount,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 7');

                // Change the count again
                await wrapper.find('button').trigger('click');
                expect(wrapper.find('.count').text()).toBe('Count: 8');
            });
        });

        describe('Multiple overrides:', () => {
            it('should be able to override ref values (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: '<div>Count: {{ count }}</div>',
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);

                                return {
                                    public: {
                                        count,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.text()).toBe('Count: 1');

                // 1. Override the setup function
                overrideComponentSetup()('originalComponent', () => {
                    return {
                        count: ref(5),
                    };
                });

                await flushPromises();

                // 2. Override the setup function
                overrideComponentSetup()('originalComponent', () => {
                    return {
                        count: ref(10),
                    };
                });

                await flushPromises();

                expect(wrapper.text()).toBe('Count: 10');
            });

            it('should be able to override ref values and access previous ones (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: '<div>Count: {{ count }}</div>',
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);

                                return {
                                    public: {
                                        count,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.text()).toBe('Count: 1');

                // 1. Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCount = previousState.count;
                    const newCount = ref(oldCount.value + 5);

                    return {
                        count: newCount,
                    };
                });

                await flushPromises();

                // 2. Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCount = previousState.count;
                    const newCount = ref(oldCount.value + 5);

                    return {
                        count: newCount,
                    };
                });

                await flushPromises();

                expect(wrapper.text()).toBe('Count: 11');
            });

            it('should be able to override ref values, access previous ones and modify previous ones (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const increment = () => {
                                    count.value += 1;
                                };

                                return {
                                    public: {
                                        count,
                                        increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');

                // Change the count
                await wrapper.find('button').trigger('click');
                expect(wrapper.find('.count').text()).toBe('Count: 2');

                // 1. Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCount = previousState.count;
                    const newCount = ref(oldCount.value + 5);

                    return {
                        count: newCount,
                    };
                });

                await flushPromises();

                // 2. Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCount = previousState.count;
                    const newCount = ref(oldCount.value + 5);

                    return {
                        count: newCount,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 12');

                // Change the count again
                await wrapper.find('button').trigger('click');
                expect(wrapper.find('.count').text()).toBe('Count: 13');
            });
        });
    });

    describe('Reactive:', () => {
        describe('Single override:', () => {
            it('should be able to override reactive values', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const complexObject = reactive({
                                    count: 1,
                                    greeting: {
                                        message: 'Hello',
                                        deep: {
                                            and: {
                                                deeper: 'Original',
                                            },
                                        },
                                    },
                                });

                                return {
                                    public: {
                                        complexObject,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Override the setup function
                overrideComponentSetup()('originalComponent', () => {
                    const newComplexObject = reactive({
                        count: 5,
                        greeting: {
                            message: 'Hi',
                            deep: {
                                and: {
                                    deeper: 'Overridden',
                                },
                            },
                        },
                    });

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 5');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hi');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Overridden');
            });

            it('should be able to override reactive values and handle missing values correctly', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const complexObject = reactive({
                                    count: 1,
                                    greeting: {
                                        message: 'Hello',
                                        deep: {
                                            and: {
                                                deeper: 'Original',
                                            },
                                        },
                                    },
                                });

                                return {
                                    public: {
                                        complexObject,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Listen to console.error messages
                const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});

                // Override the setup function with a invalid reactive object which doesn't have the same structure
                overrideComponentSetup()('originalComponent', () => {
                    const newComplexObject = reactive({
                        count: 5,
                        hello: 'world',
                    });

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                // Should console.error the error message
                expect(consoleError).toHaveBeenCalledWith(
                    '[originalComponent] Override value not working. New structure does not contain key: complexObject.greeting',
                );
            });

            it('should be able to override reactive values and handle missing values (nested) correctly', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const complexObject = reactive({
                                    count: 1,
                                    greeting: {
                                        message: 'Hello',
                                        deep: {
                                            and: {
                                                deeper: 'Original',
                                            },
                                        },
                                    },
                                });

                                return {
                                    public: {
                                        complexObject,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Listen to console.error messages
                const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});

                // Override the setup function with a invalid reactive object which doesn't have the same structure
                overrideComponentSetup()('originalComponent', () => {
                    const newComplexObject = reactive({
                        count: 5,
                        greeting: {
                            message: 'Hi',
                            wrong: {
                                and: {
                                    failure: 'Overridden',
                                },
                            },
                            deep: {
                                empty: 'nothing is here',
                            },
                        },
                    });

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                // Should console.error the error message
                expect(consoleError).toHaveBeenCalledWith(
                    '[originalComponent] Override value not working. New structure does not contain key: complexObject.greeting.deep.and',
                );
            });

            it('should be able to override reactive values and access previous ones', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const complexObject = reactive({
                                    count: 1,
                                    greeting: {
                                        message: 'Hello',
                                        deep: {
                                            and: {
                                                deeper: 'Original',
                                            },
                                        },
                                    },
                                });

                                return {
                                    public: {
                                        complexObject,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldComplexObject = previousState.complexObject;
                    const newComplexObject = reactive({
                        count: oldComplexObject.count + 5,
                        greeting: {
                            message: `${oldComplexObject.greeting.message}!`,
                            deep: {
                                and: {
                                    deeper: `${oldComplexObject.greeting.deep.and.deeper}!`,
                                },
                            },
                        },
                    });

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 6');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello!');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original!');
            });

            it('should be able to override reactive values and access previous ones and modify previous ones', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const complexObject = reactive({
                                    count: 1,
                                    greeting: {
                                        message: 'Hello',
                                        deep: {
                                            and: {
                                                deeper: 'Original',
                                            },
                                        },
                                    },
                                });

                                const increment = () => {
                                    complexObject.count += 1;
                                };

                                return {
                                    public: {
                                        complexObject: complexObject,
                                        increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Change the count
                await wrapper.find('button').trigger('click');
                expect(wrapper.find('.count').text()).toBe('Count: 2');

                // Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldComplexObject = previousState.complexObject;
                    // Multiply the count by 5 (should be 2 * 5 = 10)
                    oldComplexObject.count *= 5;

                    const newComplexObject = reactive({
                        // Add 5 to the count (should be 10 + 5 = 15)
                        count: oldComplexObject.count + 5,
                        greeting: {
                            message: `${oldComplexObject.greeting.message}!`,
                            deep: {
                                and: {
                                    deeper: `${oldComplexObject.greeting.deep.and.deeper}!`,
                                },
                            },
                        },
                    });

                    oldComplexObject.count = 5;

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 15');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello!');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original!');
            });
        });

        describe('Multiple overrides:', () => {
            it('should be able to override reactive values (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const complexObject = reactive({
                                    count: 1,
                                    greeting: {
                                        message: 'Hello',
                                        deep: {
                                            and: {
                                                deeper: 'Original',
                                            },
                                        },
                                    },
                                });

                                return {
                                    public: {
                                        complexObject,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // 1. Override the setup function
                overrideComponentSetup()('originalComponent', () => {
                    const newComplexObject = reactive({
                        count: 5,
                        greeting: {
                            message: 'Hi',
                            deep: {
                                and: {
                                    deeper: 'Overridden',
                                },
                            },
                        },
                    });

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                // 2. Override the setup function
                overrideComponentSetup()('originalComponent', () => {
                    const newComplexObject = reactive({
                        count: 10,
                        greeting: {
                            message: 'Hey',
                            deep: {
                                and: {
                                    deeper: 'Overridden Again',
                                },
                            },
                        },
                    });

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 10');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hey');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Overridden Again');
            });

            it('should be able to override reactive values and handle missing values correctly (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const complexObject = reactive({
                                    count: 1,
                                    greeting: {
                                        message: 'Hello',
                                        deep: {
                                            and: {
                                                deeper: 'Original',
                                            },
                                        },
                                    },
                                });

                                return {
                                    public: {
                                        complexObject,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Listen to console.error messages
                const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});

                // 1. Override the setup function with a invalid reactive object which doesn't have the same structure
                overrideComponentSetup()('originalComponent', () => {
                    const newComplexObject = reactive({
                        count: 5,
                        hello: 'world',
                    });

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                // Should console.error the error message
                expect(consoleError).toHaveBeenCalledWith(
                    '[originalComponent] Override value not working. New structure does not contain key: complexObject.greeting',
                );

                // 2. Override the setup function with a invalid reactive object which doesn't have the same structure
                overrideComponentSetup()('originalComponent', () => {
                    const newComplexObject = reactive({
                        count: 10,
                        greeting: {
                            message: 'Hi',
                            wrong: {
                                and: {
                                    failure: 'Overridden',
                                },
                            },
                            deep: {
                                empty: 'nothing is here',
                            },
                        },
                    });

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                // Should console.error the error message
                expect(consoleError).toHaveBeenCalledWith(
                    '[originalComponent] Override value not working. New structure does not contain key: complexObject.greeting.deep.and',
                );
            });

            it('should be able to override reactive values and handle missing values (nested) correctly (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const complexObject = reactive({
                                    count: 1,
                                    greeting: {
                                        message: 'Hello',
                                        deep: {
                                            and: {
                                                deeper: 'Original',
                                            },
                                        },
                                    },
                                });

                                return {
                                    public: {
                                        complexObject,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Listen to console.error messages
                const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});

                // 1. Override the setup function with a invalid reactive object which doesn't have the same structure
                overrideComponentSetup()('originalComponent', () => {
                    const newComplexObject = reactive({
                        count: 5,
                        greeting: {
                            message: 'Hi',
                            wrong: {
                                and: {
                                    failure: 'Overridden',
                                },
                            },
                            deep: {
                                empty: 'nothing is here',
                            },
                        },
                    });

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                // Should console.error the error message
                expect(consoleError).toHaveBeenCalledWith(
                    '[originalComponent] Override value not working. New structure does not contain key: complexObject.greeting.deep.and',
                );

                // 2. Override the setup function with a invalid reactive object which doesn't have the same structure
                overrideComponentSetup()('originalComponent', () => {
                    const newComplexObject = reactive({
                        count: 10,
                        greeting: {
                            message: 'Hey',
                            wrong: {
                                and: {
                                    failure: 'Overridden again',
                                },
                            },
                            deep: {
                                // Have the end key in the second override
                                and: {
                                    empty: 'nothing is here again',
                                },
                            },
                        },
                    });

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                // Should console.error the error message
                expect(consoleError).toHaveBeenCalledWith(
                    '[originalComponent] Override value not working. New structure does not contain key: complexObject.greeting.deep.and.deeper',
                );
            });

            it('should be able to override reactive values and access previous ones (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const complexObject = reactive({
                                    count: 1,
                                    greeting: {
                                        message: 'Hello',
                                        deep: {
                                            and: {
                                                deeper: 'Original',
                                            },
                                        },
                                    },
                                });

                                return {
                                    public: {
                                        complexObject,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // 1. Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldComplexObject = previousState.complexObject;
                    const newComplexObject = reactive({
                        count: oldComplexObject.count + 5,
                        greeting: {
                            message: `${oldComplexObject.greeting.message}!`,
                            deep: {
                                and: {
                                    deeper: `${oldComplexObject.greeting.deep.and.deeper}!`,
                                },
                            },
                        },
                    });

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                // 2. Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldComplexObject = previousState.complexObject;
                    const newComplexObject = reactive({
                        count: oldComplexObject.count + 5,
                        greeting: {
                            message: `${oldComplexObject.greeting.message}!!`,
                            deep: {
                                and: {
                                    deeper: `${oldComplexObject.greeting.deep.and.deeper}!!`,
                                },
                            },
                        },
                    });

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 11');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello!!!');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original!!!');
            });

            it('should be able to override reactive values and access previous ones and modify previous ones (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const complexObject = reactive({
                                    count: 1,
                                    greeting: {
                                        message: 'Hello',
                                        deep: {
                                            and: {
                                                deeper: 'Original',
                                            },
                                        },
                                    },
                                });

                                const increment = () => {
                                    complexObject.count += 1;
                                };

                                return {
                                    public: {
                                        complexObject: complexObject,
                                        increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Change the count
                await wrapper.find('button').trigger('click');
                expect(wrapper.find('.count').text()).toBe('Count: 2');

                // 1. Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldComplexObject = previousState.complexObject;
                    // Multiply the count by 5 (should be 2 * 5 = 10)
                    oldComplexObject.count *= 5;

                    const newComplexObject = reactive({
                        // Add 5 to the count (should be 10 + 5 = 15)
                        count: oldComplexObject.count + 5,
                        greeting: {
                            message: `${oldComplexObject.greeting.message}!`,
                            deep: {
                                and: {
                                    deeper: `${oldComplexObject.greeting.deep.and.deeper}!`,
                                },
                            },
                        },
                    });

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                // 2. Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldComplexObject = previousState.complexObject;
                    // Multiply the count by 5 (should be ((2 * 5) + 5) * 5 = 75)
                    oldComplexObject.count *= 5;

                    const newComplexObject = reactive({
                        // Add 5 to the count (should be 75 + 5 = 80)
                        count: oldComplexObject.count + 5,
                        greeting: {
                            message: `${oldComplexObject.greeting.message}!`,
                            deep: {
                                and: {
                                    deeper: `${oldComplexObject.greeting.deep.and.deeper}!`,
                                },
                            },
                        },
                    });

                    return {
                        complexObject: newComplexObject,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 80');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello!!');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original!!');
            });
        });
    });

    describe('Computed:', () => {
        describe('Single override:', () => {
            it('should be able to override readonly computed values', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const countDoubled = computed(() => count.value * 2);
                                const increment = () => {
                                    count.value += 1;
                                };

                                return {
                                    public: {
                                        count: count,
                                        countDoubled: countDoubled,
                                        increment: increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');

                // Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const countTripled = computed(() => previousState.count.value * 3);

                    return {
                        countDoubled: countTripled,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                // Should be tripled now because we overrode the countDoubled computed property
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 3');
            });

            it('should be able to override readonly computed values and access previous ones', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                        <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const countDoubled = computed(() => count.value * 2);
                                const countTripled = computed(() => count.value * 3);
                                const increment = () => {
                                    count.value += 1;
                                };

                                return {
                                    public: {
                                        count: count,
                                        countDoubled: countDoubled,
                                        countTripled: countTripled,
                                        increment: increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 3');

                // Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCountDoubled = previousState.countDoubled;
                    const countTimesSix = computed(() => oldCountDoubled.value * 3);

                    return {
                        countTripled: countTimesSix,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                // Should be tripled now because we overrode the countDoubled computed property
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                // Should be multiplied by 6 now because we overrode the countDoubled computed property
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 6');
            });

            it('should be able to override readonly computed values and modify previous ones', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                        <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const countDoubled = computed(() => count.value * 2);
                                const countTripled = computed(() => count.value * 3);
                                const increment = () => {
                                    count.value += 1;
                                };

                                return {
                                    public: {
                                        count: count,
                                        countDoubled: countDoubled,
                                        countTripled: countTripled,
                                        increment: increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 3');

                // Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCountDoubled = previousState.countDoubled;
                    const countTimesSix = computed(() => oldCountDoubled.value * 3);
                    const oldCount = previousState.count;
                    oldCount.value = 5;

                    return {
                        countTripled: countTimesSix,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 5');
                // Should be tripled now because we overrode the countDoubled computed property
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 10');
                // Should be multiplied by 6 now because we overrode the countDoubled computed property
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 30');
            });

            it('should be able to override writable computed values', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                        <input v-model="countDoubled" type="number"/>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const countDoubled = computed({
                                    get: () => count.value * 2,
                                    set: (value) => {
                                        count.value = value / 2;
                                    },
                                });

                                return {
                                    public: {
                                        count: count,
                                        countDoubled: countDoubled,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');

                // Change the countDoubled value
                await wrapper.find('input').setValue(10);
                expect(wrapper.find('.count').text()).toBe('Count: 5');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 10');

                // Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const countTripled = computed({
                        get: () => previousState.count.value * 3,
                        set: (value) => {
                            previousState.count.value = value / 3;
                        },
                    });

                    return {
                        countDoubled: countTripled,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 5');
                // Should be tripled now because we overrode the countDoubled computed property
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 15');
            });

            it('should be able to override writable computed values and access previous ones', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                        <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                        <input v-model="countTripled" type="number"/>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const countDoubled = computed({
                                    get: () => count.value * 2,
                                    set: (value) => {
                                        count.value = value / 2;
                                    },
                                });
                                const countTripled = computed({
                                    get: () => count.value * 3,
                                    set: (value) => {
                                        count.value = value / 3;
                                    },
                                });

                                return {
                                    public: {
                                        count: count,
                                        countDoubled: countDoubled,
                                        countTripled: countTripled,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 3');

                // Change the countDoubled value
                await wrapper.find('input').setValue(9);
                expect(wrapper.find('.count').text()).toBe('Count: 3');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 6');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 9');

                // Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCountTripled = previousState.countTripled;
                    const countTimesSix = computed({
                        get: () => oldCountTripled.value * 2,
                        set: (value) => {
                            oldCountTripled.value = value / 2;
                        },
                    });

                    return {
                        countTripled: countTimesSix,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 3');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 6');
                // Should be multiplied by 6 now because we overrode the countDoubled computed property
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 18');
            });

            it('should be able to override writable computed values and modify previous ones', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                        <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                        <input v-model="countTripled" type="number"/>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const countDoubled = computed({
                                    get: () => count.value * 2,
                                    set: (value) => {
                                        count.value = value / 2;
                                    },
                                });
                                const countTripled = computed({
                                    get: () => count.value * 3,
                                    set: (value) => {
                                        count.value = value / 3;
                                    },
                                });

                                return {
                                    public: {
                                        count: count,
                                        countDoubled: countDoubled,
                                        countTripled: countTripled,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 3');

                // Change the countDoubled value
                await wrapper.find('input').setValue(9);
                expect(wrapper.find('.count').text()).toBe('Count: 3');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 6');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 9');

                // Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCountTripled = previousState.countTripled;
                    const countTimesSix = computed({
                        get: () => oldCountTripled.value * 2,
                        set: (value) => {
                            oldCountTripled.value = value / 2;
                        },
                    });

                    // Modify the previous writable computed value
                    previousState.countDoubled.value = 2;

                    return {
                        countTripled: countTimesSix,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                // Should be multiplied by 6 now because we overrode the countDoubled computed property
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 6');
            });
        });

        describe('Multiple overrides:', () => {
            it('should be able to override readonly computed values (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const countDoubled = computed(() => count.value * 2);
                                const increment = () => {
                                    count.value += 1;
                                };

                                return {
                                    public: {
                                        count: count,
                                        countDoubled: countDoubled,
                                        increment: increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');

                // First override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const countTripled = computed(() => previousState.count.value * 3);

                    return {
                        countDoubled: countTripled,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 3');

                // Second override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const countQuadrupled = computed(() => previousState.count.value * 4);

                    return {
                        countDoubled: countQuadrupled,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 4');
            });

            it('should be able to override readonly computed values and access previous ones (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                    <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const countDoubled = computed(() => count.value * 2);
                                const countTripled = computed(() => count.value * 3);
                                const increment = () => {
                                    count.value += 1;
                                };

                                return {
                                    public: {
                                        count: count,
                                        countDoubled: countDoubled,
                                        countTripled: countTripled,
                                        increment: increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 3');

                // First override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCountDoubled = previousState.countDoubled;
                    const countTimesFour = computed(() => oldCountDoubled.value * 2);

                    return {
                        countTripled: countTimesFour,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 4');

                // Second override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCountTripled = previousState.countTripled;
                    const countTimesSix = computed(() => oldCountTripled.value * 1.5);

                    return {
                        countTripled: countTimesSix,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 6');
            });

            it('should be able to override readonly computed values and modify previous ones (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                    <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const countDoubled = computed(() => count.value * 2);
                                const countTripled = computed(() => count.value * 3);
                                const increment = () => {
                                    count.value += 1;
                                };

                                return {
                                    public: {
                                        count: count,
                                        countDoubled: countDoubled,
                                        countTripled: countTripled,
                                        increment: increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 3');

                // First override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCountDoubled = previousState.countDoubled;
                    const countTimesFour = computed(() => oldCountDoubled.value * 2);
                    previousState.count.value = 2;

                    return {
                        countTripled: countTimesFour,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 2');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 4');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 8');

                // Second override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCountTripled = previousState.countTripled;
                    const countTimesSix = computed(() => oldCountTripled.value * 1.5);
                    previousState.count.value = 3;

                    return {
                        countTripled: countTimesSix,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 3');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 6');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 18');
            });

            it('should be able to override writable computed values (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                    <input v-model="countDoubled" type="number"/>
                `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const countDoubled = computed({
                                    get: () => count.value * 2,
                                    set: (value) => {
                                        count.value = value / 2;
                                    },
                                });

                                return {
                                    public: {
                                        count: count,
                                        countDoubled: countDoubled,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');

                // First override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const countTripled = computed({
                        get: () => previousState.count.value * 3,
                        set: (value) => {
                            previousState.count.value = value / 3;
                        },
                    });

                    return {
                        countDoubled: countTripled,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 3');

                // Second override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const countQuadrupled = computed({
                        get: () => previousState.count.value * 4,
                        set: (value) => {
                            previousState.count.value = value / 4;
                        },
                    });

                    return {
                        countDoubled: countQuadrupled,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 4');

                // Change the countDoubled value
                await wrapper.find('input').setValue(20);
                expect(wrapper.find('.count').text()).toBe('Count: 5');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 20');
            });

            it('should be able to override writable computed values and access previous ones (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                    <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                    <input v-model="countTripled" type="number"/>
                `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const countDoubled = computed({
                                    get: () => count.value * 2,
                                    set: (value) => {
                                        count.value = value / 2;
                                    },
                                });
                                const countTripled = computed({
                                    get: () => count.value * 3,
                                    set: (value) => {
                                        count.value = value / 3;
                                    },
                                });

                                return {
                                    public: {
                                        count: count,
                                        countDoubled: countDoubled,
                                        countTripled: countTripled,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 3');

                // First override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCountTripled = previousState.countTripled;
                    const countTimesSix = computed({
                        get: () => oldCountTripled.value * 2,
                        set: (value) => {
                            oldCountTripled.value = value / 2;
                        },
                    });

                    return {
                        countTripled: countTimesSix,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 6');

                // Second override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCountTripled = previousState.countTripled;
                    const countTimesNine = computed({
                        get: () => oldCountTripled.value * 1.5,
                        set: (value) => {
                            oldCountTripled.value = value / 1.5;
                        },
                    });

                    return {
                        countTripled: countTimesNine,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 9');

                // Change the countTripled value
                await wrapper.find('input').setValue(18);
                expect(wrapper.find('.count').text()).toBe('Count: 2');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 4');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 18');
            });

            it('should be able to override writable computed values and modify previous ones (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                    <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                    <input v-model="countTripled" type="number"/>
                `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const countDoubled = computed({
                                    get: () => count.value * 2,
                                    set: (value) => {
                                        count.value = value / 2;
                                    },
                                });
                                const countTripled = computed({
                                    get: () => count.value * 3,
                                    set: (value) => {
                                        count.value = value / 3;
                                    },
                                });

                                return {
                                    public: {
                                        count: count,
                                        countDoubled: countDoubled,
                                        countTripled: countTripled,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 3');

                // First override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCountTripled = previousState.countTripled;
                    const countTimesSix = computed({
                        get: () => oldCountTripled.value * 2,
                        set: (value) => {
                            oldCountTripled.value = value / 2;
                        },
                    });

                    previousState.count.value = 2;

                    return {
                        countTripled: countTimesSix,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 2');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 4');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 12');

                // Second override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const oldCountTripled = previousState.countTripled;
                    const countTimesNine = computed({
                        get: () => oldCountTripled.value * 1.5,
                        set: (value) => {
                            oldCountTripled.value = value / 1.5;
                        },
                    });

                    previousState.count.value = 3;

                    return {
                        countTripled: countTimesNine,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 3');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 6');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 27');

                // Change the countTripled value
                await wrapper.find('input').setValue(54);
                expect(wrapper.find('.count').text()).toBe('Count: 6');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 12');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 54');
            });
        });
    });

    describe('Functions:', () => {
        describe('Single override:', () => {
            it('should be able to override functions', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const increment = () => {
                                    count.value += 1;
                                };

                                return {
                                    public: {
                                        count: count,
                                        increment: increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');

                // Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const previousIncrement = previousState.increment;

                    const newIncrement = () => {
                        // Call previous increment function twice
                        previousIncrement();
                        previousIncrement();
                    };

                    return {
                        increment: newIncrement,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');

                await wrapper.find('button').trigger('click');
                // The increment function should be overridden and should increment the count by 2
                expect(wrapper.find('.count').text()).toBe('Count: 3');
            });

            it('should be able to override functions (with direct call of the previousState method)', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const increment = () => {
                                    count.value += 1;
                                };

                                return {
                                    public: {
                                        count: count,
                                        increment: increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');

                // Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const newIncrement = () => {
                        // Call previous increment function twice
                        previousState.increment();
                        previousState.increment();
                    };

                    return {
                        increment: newIncrement,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');

                await wrapper.find('button').trigger('click');
                // The increment function should be overridden and should increment the count by 2
                expect(wrapper.find('.count').text()).toBe('Count: 3');
            });
        });

        describe('Multiple overrides:', () => {
            it('should be able to override functions (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const increment = () => {
                                    count.value += 1;
                                };

                                return {
                                    public: {
                                        count: count,
                                        increment: increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');

                // First override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const previousIncrement = previousState.increment;

                    const newIncrement = () => {
                        // Call previous increment function twice
                        previousIncrement();
                        previousIncrement();
                    };

                    return {
                        increment: newIncrement,
                    };
                });

                await flushPromises();

                // Second override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const previousIncrement = previousState.increment;

                    const newIncrement = () => {
                        // Call previous increment function and add 1 more
                        previousIncrement();
                        previousState.count.value += 1;
                    };

                    return {
                        increment: newIncrement,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');

                await wrapper.find('button').trigger('click');
                // The increment function should be overridden twice and should increment the count by 3
                // (+2 from the first override which is calling the original twice and +1 from the second override)
                expect(wrapper.find('.count').text()).toBe('Count: 4');
            });

            it('should be able to override functions (with direct call of the previousState method, multiple overrides)', async () => {
                const originalComponent = defineComponent({
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const increment = () => {
                                    count.value += 1;
                                };

                                return {
                                    public: {
                                        count: count,
                                        increment: increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');

                // 1. Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const newIncrement = () => {
                        // Call previous increment function twice
                        previousState.increment();
                        previousState.increment();
                    };

                    return {
                        increment: newIncrement,
                    };
                });

                await flushPromises();

                // 2. Override the setup function
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const newIncrement = () => {
                        // Call previous increment function and add 1 more
                        previousState.increment();
                        previousState.count.value += 1;
                    };

                    return {
                        increment: newIncrement,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                await wrapper.find('button').trigger('click');

                // The increment function should be overridden twice and should increment the count by 3
                // (+2 from the first override which is calling the original twice and +1 from the second override)
                expect(wrapper.find('.count').text()).toBe('Count: 4');
            });

            it('should be able to override functions and access previous ones (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="double-count">Double Count: {{ doubleCount }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const doubleCount = ref(2);
                                const increment = () => {
                                    count.value += 1;
                                    doubleCount.value = count.value * 2;
                                };

                                return {
                                    public: {
                                        count: count,
                                        doubleCount: doubleCount,
                                        increment: increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.double-count').text()).toBe('Double Count: 2');

                // First override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const previousIncrement = previousState.increment;

                    const newIncrement = () => {
                        previousIncrement();
                        previousState.doubleCount.value *= 2;
                    };

                    return {
                        increment: newIncrement,
                    };
                });

                await flushPromises();

                // Second override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const previousIncrement = previousState.increment;

                    const newIncrement = () => {
                        previousIncrement();
                        previousState.count.value += 1;
                    };

                    return {
                        increment: newIncrement,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.double-count').text()).toBe('Double Count: 2');

                await wrapper.find('button').trigger('click');
                expect(wrapper.find('.count').text()).toBe('Count: 3');
                expect(wrapper.find('.double-count').text()).toBe('Double Count: 8');
            });

            it('should be able to override functions and modify previous ones (Multiple overridess)', async () => {
                const originalComponent = defineComponent({
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="double-count">Double Count: {{ doubleCount }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: (props, context) =>
                        createExtendableSetup(
                            {
                                props,
                                context,
                                name: 'originalComponent',
                            },
                            () => {
                                const count = ref(1);
                                const doubleCount = ref(2);
                                const increment = () => {
                                    count.value += 1;
                                    doubleCount.value = count.value * 2;
                                };

                                return {
                                    public: {
                                        count: count,
                                        doubleCount: doubleCount,
                                        increment: increment,
                                    },
                                };
                            },
                        ),
                });

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.double-count').text()).toBe('Double Count: 2');

                // First override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const previousIncrement = previousState.increment;

                    const newIncrement = () => {
                        previousIncrement();
                        previousState.count.value += 1;
                        previousState.doubleCount.value *= 2;
                    };

                    return {
                        increment: newIncrement,
                    };
                });

                await flushPromises();

                // Second override
                overrideComponentSetup()('originalComponent', (previousState) => {
                    const previousIncrement = previousState.increment;

                    const newIncrement = () => {
                        previousIncrement();
                        previousState.count.value *= 2;
                        previousState.doubleCount.value += 5;
                    };

                    return {
                        increment: newIncrement,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.double-count').text()).toBe('Double Count: 2');

                await wrapper.find('button').trigger('click');
                // The increment function should be overridden twice and should increment the count by + 1, +1 and * 2
                expect(wrapper.find('.count').text()).toBe('Count: 6');

                // The doubleCount should be incremented by * 2, * 2 and + 5
                expect(wrapper.find('.double-count').text()).toBe('Double Count: 13');
            });
        });
    });

    describe('Props:', () => {
        it('should be able to access props in the override setup function', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="multiplier">Multiplier: {{ multiplier }}</div>
                    <div class="multiplied">Multiplied: {{ multipliedCount }}</div>
                `,
                props: {
                    multiplier: {
                        type: Number,
                        default: 1,
                    },
                },
                setup: (props, context) =>
                    createExtendableSetup(
                        {
                            props,
                            context,
                            name: 'originalComponent',
                        },
                        () => {
                            const count = ref(1);
                            const multipliedCount = computed(() => count.value * props.multiplier);

                            return {
                                public: {
                                    count,
                                    multipliedCount,
                                },
                            };
                        },
                    ),
            });

            const wrapper = mount(originalComponent, {
                props: {
                    multiplier: 2,
                },
            });

            expect(wrapper.find('.count').text()).toBe('Count: 1');
            expect(wrapper.find('.multiplier').text()).toBe('Multiplier: 2');
            expect(wrapper.find('.multiplied').text()).toBe('Multiplied: 2');

            overrideComponentSetup<typeof originalComponent>()('originalComponent', (previousState, props) => {
                const newCount = ref(5);
                // Multiply by the multiplier prop and then multiply by 2
                const newMultipliedCount = computed(() => newCount.value * props.multiplier! * 2);

                return {
                    count: newCount,
                    multipliedCount: newMultipliedCount,
                };
            });

            await flushPromises();

            expect(wrapper.find('.count').text()).toBe('Count: 5');
            expect(wrapper.find('.multiplier').text()).toBe('Multiplier: 2');
            // Should be multiplied by 2 and the props.multiplier now because we overrode the multipliedCount computed property
            expect(wrapper.find('.multiplied').text()).toBe('Multiplied: 20');
        });

        it('should update when props change after override', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="multiplier">Multiplier: {{ multiplier }}</div>
                    <div class="multiplied">Multiplied: {{ multipliedCount }}</div>
                `,
                props: {
                    multiplier: {
                        type: Number,
                        default: 1,
                    },
                },
                setup: (props, context) =>
                    createExtendableSetup(
                        {
                            props,
                            context,
                            name: 'originalComponent',
                        },
                        () => {
                            const count = ref(1);
                            const multipliedCount = computed(() => count.value * props.multiplier);

                            return {
                                public: {
                                    count,
                                    multipliedCount,
                                },
                            };
                        },
                    ),
            });

            const wrapper = mount(originalComponent, {
                props: {
                    multiplier: 2,
                },
            });

            expect(wrapper.find('.count').text()).toBe('Count: 1');
            expect(wrapper.find('.multiplier').text()).toBe('Multiplier: 2');
            expect(wrapper.find('.multiplied').text()).toBe('Multiplied: 2');

            overrideComponentSetup<typeof originalComponent>()('originalComponent', (previousState, props) => {
                const newCount = ref(5);
                // Multiply by the multiplier prop and then multiply by 2
                const newMultipliedCount = computed(() => newCount.value * props.multiplier! * 2);

                return {
                    count: newCount,
                    multipliedCount: newMultipliedCount,
                };
            });

            await flushPromises();

            expect(wrapper.find('.count').text()).toBe('Count: 5');
            expect(wrapper.find('.multiplier').text()).toBe('Multiplier: 2');
            // Should be multiplied by 2 and the props.multiplier now because we overrode the multipliedCount computed property
            expect(wrapper.find('.multiplied').text()).toBe('Multiplied: 20');

            await wrapper.setProps({ multiplier: 3 });

            expect(wrapper.find('.count').text()).toBe('Count: 5');
            expect(wrapper.find('.multiplier').text()).toBe('Multiplier: 3');
            // Should be multiplied by 2 and the props.multiplier now because we overrode the multipliedCount computed property
            expect(wrapper.find('.multiplied').text()).toBe('Multiplied: 30');
        });

        it('should handle multiple overrides with props', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="base">Base: {{ baseValue }}</div>
                    <div class="multiplier">Multiplier: {{ multiplier }}</div>
                    <div class="multiplied">Multiplied: {{ multipliedValue }}</div>
                    <div class="addedValue">Added value: {{ addedValue }}</div>
                    <div class="added">Added: {{ added }}</div>
                `,
                props: {
                    multiplier: {
                        type: Number,
                        default: 1,
                    },
                    added: {
                        type: Number,
                        default: 0,
                    },
                },
                setup: (props, context) =>
                    createExtendableSetup(
                        {
                            props,
                            context,
                            name: 'originalComponent',
                        },
                        () => {
                            const baseValue = ref(1);
                            const multipliedValue = computed(() => baseValue.value * props.multiplier);
                            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                            const addedValue = computed(() => baseValue.value + props.added);

                            return {
                                public: {
                                    baseValue,
                                    multipliedValue,
                                    addedValue,
                                },
                            };
                        },
                    ),
            });

            const wrapper = mount(originalComponent, {
                props: {
                    multiplier: 2,
                    added: 3,
                },
            });

            expect(wrapper.find('.base').text()).toBe('Base: 1');
            expect(wrapper.find('.multiplier').text()).toBe('Multiplier: 2');
            expect(wrapper.find('.multiplied').text()).toBe('Multiplied: 2');
            expect(wrapper.find('.added').text()).toBe('Added: 3');
            expect(wrapper.find('.addedValue').text()).toBe('Added value: 4');

            overrideComponentSetup<typeof originalComponent>()('originalComponent', (previousState, props) => {
                const newBaseValue = ref(5);
                const newMultipliedValue = computed(() => newBaseValue.value * props.multiplier!);

                return {
                    baseValue: newBaseValue,
                    multipliedValue: newMultipliedValue,
                };
            });

            await flushPromises();

            expect(wrapper.find('.base').text()).toBe('Base: 5');
            expect(wrapper.find('.multiplier').text()).toBe('Multiplier: 2');
            expect(wrapper.find('.multiplied').text()).toBe('Multiplied: 10');
            expect(wrapper.find('.added').text()).toBe('Added: 3');
            expect(wrapper.find('.addedValue').text()).toBe('Added value: 8');

            overrideComponentSetup<typeof originalComponent>()('originalComponent', (previousState, props) => {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                const newAddedValue = computed(() => previousState.baseValue.value + props.added! * 2);

                return {
                    addedValue: newAddedValue,
                };
            });

            await flushPromises();

            expect(wrapper.find('.base').text()).toBe('Base: 5');
            expect(wrapper.find('.multiplier').text()).toBe('Multiplier: 2');
            expect(wrapper.find('.multiplied').text()).toBe('Multiplied: 10');
            expect(wrapper.find('.added').text()).toBe('Added: 3');
            // Should be added by 3 * 2 because we overrode the addedValue computed property, so 5 + 6 = 11
            expect(wrapper.find('.addedValue').text()).toBe('Added value: 11');

            await wrapper.setProps({ multiplier: 3, added: 4 });

            expect(wrapper.find('.base').text()).toBe('Base: 5');
            expect(wrapper.find('.multiplier').text()).toBe('Multiplier: 3');
            expect(wrapper.find('.multiplied').text()).toBe('Multiplied: 15');
            expect(wrapper.find('.added').text()).toBe('Added: 4');
            // Should be added by 4 * 2 because we overrode the addedValue computed property, so 5 + 8 = 13
            expect(wrapper.find('.addedValue').text()).toBe('Added value: 13');
        });

        it('should console an error when the original setup functions returns a prop', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="multiplier">Multiplier: {{ multiplier }}</div>
                    <div class="multiplied">Multiplied: {{ multipliedCount }}</div>
                `,
                props: {
                    multiplier: {
                        type: Number,
                        default: 1,
                    },
                },
                setup: (props, context) =>
                    createExtendableSetup(
                        {
                            props,
                            context,
                            name: 'originalComponent',
                        },
                        () => {
                            const count = ref(1);
                            const multipliedCount = computed(() => count.value * props.multiplier);

                            return {
                                public: {
                                    count,
                                    multipliedCount,
                                    // This is not allowed and should cause an error
                                    multiplier: props.multiplier,
                                },
                            };
                        },
                    ),
            });

            // Mock console.error
            const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});

            mount(originalComponent, {
                props: {
                    multiplier: 2,
                },
            });

            await flushPromises();

            expect(consoleError).toHaveBeenCalledWith(
                '[originalComponent] The original setup function for the originalComponent component returned a prop. This is not allowed. Props are only available for overrides with the second argument.',
            );
        });

        it('should console an error when the override function returns a prop', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="multiplier">Multiplier: {{ multiplier }}</div>
                    <div class="multiplied">Multiplied: {{ multipliedCount }}</div>
                `,
                props: {
                    multiplier: {
                        type: Number,
                        default: 1,
                    },
                },
                setup: (props, context) =>
                    createExtendableSetup(
                        {
                            props,
                            context,
                            name: 'originalComponent',
                        },
                        () => {
                            const count = ref(1);
                            const multipliedCount = computed(() => count.value * props.multiplier);

                            return {
                                public: {
                                    count,
                                    multipliedCount,
                                    // This is not allowed and should cause an error
                                    multiplier: props.multiplier,
                                },
                            };
                        },
                    ),
            });

            mount(originalComponent, {
                props: {
                    multiplier: 2,
                },
            });

            // Mock console.error
            const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});

            // Override the setup function
            overrideComponentSetup<typeof originalComponent>()('originalComponent', (previousState, props) => {
                const newCount = ref(5);
                // Multiply by the multiplier prop and then multiply by 2
                const newMultipliedCount = computed(() => newCount.value * props.multiplier! * 2);

                return {
                    count: newCount,
                    multipliedCount: newMultipliedCount,
                    // This is not allowed and should cause an error
                    multiplier: props.multiplier,
                };
            });

            await flushPromises();

            expect(consoleError).toHaveBeenCalledWith(
                '[originalComponent] Override result value not working. Cannot override props. Following prop should be changed: "multiplier"',
            );
        });
    });

    describe('Context:', () => {
        it('should be able to access context in the override', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div>
                        <slot name="header">
                            {{ message }}
                        </slot>
                        {{ secondMessage }}
                    </div>
                `,
                setup: (props, context) =>
                    createExtendableSetup(
                        {
                            props,
                            context,
                            name: 'originalComponent',
                        },
                        () => {
                            const message = ref('Original message');
                            const secondMessage = ref('Original second message');

                            return {
                                public: {
                                    message,
                                    secondMessage,
                                },
                            };
                        },
                    ),
            });

            const wrapper = mount(originalComponent, {
                slots: {
                    header: 'Original Header',
                },
                attrs: {
                    title: 'Original Title',
                },
            });

            expect(wrapper.text()).toBe('Original Header Original second message');

            overrideComponentSetup()('originalComponent', (previousState, props, context) => {
                // Access slots
                const headerSlot = context.slots.header;

                // Access attrs
                const title = (context.attrs.title as string) ?? '';

                const newSecondMessage = ref(`Overriden: Title: ${title}. Header slot filled: ${!!headerSlot}`);

                return {
                    secondMessage: newSecondMessage,
                };
            });

            await flushPromises();

            expect(wrapper.text()).toBe('Original Header Overriden: Title: Original Title. Header slot filled: true');
        });

        it('should be able to access context in the override (with empty slot)', async () => {
            const originalComponent = defineComponent({
                template: `
                <div>
                    <slot name="header">
                        {{ message }}
                    </slot>
                    {{ secondMessage }}
                </div>
            `,
                setup: (props, context) =>
                    createExtendableSetup(
                        {
                            props,
                            context,
                            name: 'originalComponent',
                        },
                        () => {
                            const message = ref('Original message');
                            const secondMessage = ref('Original second message');

                            return {
                                public: {
                                    message,
                                    secondMessage,
                                },
                            };
                        },
                    ),
            });

            const wrapper = mount(originalComponent, {
                attrs: {
                    title: 'Original Title',
                },
            });

            expect(wrapper.text()).toBe('Original message Original second message');

            overrideComponentSetup()('originalComponent', (previousState, props, context) => {
                // Access slots
                const headerSlot = context.slots.header;

                // Access attrs
                const title = (context.attrs.title as string) ?? '';

                const newSecondMessage = ref(`Overriden: Title: ${title}. Header slot filled: ${!!headerSlot}`);

                return {
                    secondMessage: newSecondMessage,
                };
            });

            await flushPromises();

            expect(wrapper.text()).toBe('Original message Overriden: Title: Original Title. Header slot filled: false');
        });

        it('should be able to modify exposed properties using context.expose', async () => {
            const originalComponent = defineComponent({
                template: '<div>{{ exposedValue }}</div>',
                setup: (props, context) =>
                    createExtendableSetup(
                        {
                            props,
                            context,
                            name: 'originalComponent',
                        },
                        () => {
                            const exposedValue = ref('Original');

                            return {
                                public: {
                                    exposedValue,
                                },
                            };
                        },
                    ),
            });

            const wrapper = mount(originalComponent);

            overrideComponentSetup()('originalComponent', () => {
                const newExposedValue = ref('Overridden');

                return {
                    exposedValue: newExposedValue,
                };
            });

            await flushPromises();

            expect(wrapper.vm.exposedValue).toBe('Overridden');
            expect(wrapper.text()).toBe('Overridden');
        });
    });

    describe('Private and Public API:', () => {
        it('should be able to directly access public values', async () => {
            const originalComponent = defineComponent({
                template: '<div>Count: {{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup(
                        {
                            props,
                            context,
                            name: 'originalComponent',
                        },
                        () => {
                            const count = ref(1);
                            const thisIsPrivate = ref('Private');

                            return {
                                private: {
                                    thisIsPrivate,
                                },
                                public: {
                                    count,
                                },
                            };
                        },
                    ),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.text()).toBe('Count: 1');

            // Override the setup function
            overrideComponentSetup()('originalComponent', (previousState) => {
                const oldCount = previousState.count;
                const newCount = ref(oldCount.value + 5);

                return {
                    count: newCount,
                };
            });

            await flushPromises();

            expect(wrapper.text()).toBe('Count: 6');
        });

        it('should be able to access private values using _private prefix', async () => {
            const originalComponent = defineComponent({
                template: '<div>Private: {{ thisIsPrivate }}</div>',
                setup: (props, context) =>
                    createExtendableSetup(
                        {
                            props,
                            context,
                            name: 'originalComponent',
                        },
                        () => {
                            const count = ref(1);
                            const thisIsPrivate = ref('Private');

                            return {
                                private: {
                                    thisIsPrivate,
                                },
                                public: {
                                    count,
                                },
                            };
                        },
                    ),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.text()).toBe('Private: Private');

            // Override the setup function
            overrideComponentSetup()('originalComponent', (previousState) => {
                const oldThisIsPrivate = previousState._private.thisIsPrivate;
                const newThisIsPrivate = ref(`${oldThisIsPrivate.value} from plugin`);

                return {
                    thisIsPrivate: newThisIsPrivate,
                };
            });

            await flushPromises();

            expect(wrapper.text()).toBe('Private: Private from plugin');
        });
    });

    describe('TS Types', () => {
        /* eslint-disable jest/expect-expect */
        /**
         * These are just type checks without any runtime assertions
         */
        it('should have correct props types in createExtendableSetup', () => {
            defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="multiplier>Multiplier: {{ multiplier }}</div>
                    <div class="multiplied>Multiplied: {{ multipliedCount }}</div>
                `,
                props: {
                    multiplier: {
                        type: Number,
                        default: 1,
                    },
                    title: {
                        type: String,
                        default: 'Original',
                    },
                    complexProp: {
                        type: Object as PropType<{
                            hello: string;
                            world: number;
                        }>,
                        default: () => ({}),
                    },
                },
                setup: (props, context) =>
                    createExtendableSetup(
                        {
                            props,
                            context,
                            name: 'originalComponent',
                        },
                        () => {
                            const count = ref(1);
                            const multipliedCount = computed(() => count.value * props.multiplier);

                            // Check if the props types are correct
                            expectType<number>(props.multiplier);
                            expectType<string>(props.title);
                            expectType<{ hello: string; world: number }>(props.complexProp);

                            return {
                                public: {
                                    count,
                                    multipliedCount,
                                },
                            };
                        },
                    ),
            });
        });

        it('should have correct previousState types for the overrideComponentSetup', () => {
            const _InternalTestComponent = defineComponent({
                template: `
                    <div class="base">Base: {{ baseValue }}</div>
                    <div class="multiplier">Multiplier: {{ multiplier }}</div>
                    <div class="multiplied">Multiplied: {{ multipliedValue }}</div>
                    <div class="addedValue">Added value: {{ addedValue }}</div>
                    <div class="added">Added: {{ added }}</div>
                `,
                props: {
                    multiplier: {
                        type: Number,
                        default: 1,
                    },
                    added: {
                        type: Number,
                        default: 0,
                    },
                },
                setup: (props, context) =>
                    createExtendableSetup(
                        {
                            props,
                            context,
                            name: '_internal_test_component',
                        },
                        () => {
                            const baseValue = ref(1);
                            const title = ref('Original Title');
                            const multipliedValue = computed(() => baseValue.value * props.multiplier);
                            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                            const addedValue = computed(() => baseValue.value + props.added);
                            const privateValue = ref('Private');

                            return {
                                private: {
                                    privateValue,
                                },
                                public: {
                                    baseValue,
                                    multipliedValue,
                                    addedValue,
                                    title,
                                },
                            };
                        },
                    ),
            });

            overrideComponentSetup<typeof _InternalTestComponent>()('_internal_test_component', (previousState, props) => {
                const newBaseValue = ref(5);
                const newMultipliedValue = computed(() => newBaseValue.value * props.multiplier!);

                previousState.baseValue.value = 2;

                // Public values are typed correctly
                expectType<number>(previousState.baseValue.value);
                expectType<number>(previousState.multipliedValue.value);
                expectType<number>(previousState.addedValue.value);
                expectType<string>(previousState.title.value);

                // Private values shouldn't be typed

                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                expectType<string>(previousState.private.privateValue.value);
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                expectType<string>(previousState.privateValue.value);

                return {
                    baseValue: newBaseValue,
                    multipliedValue: newMultipliedValue,
                };
            });
        });

        it('should return the correct merged properties from createExtendableSetup', () => {
            const props = {
                multiplier: 1,
                added: 2,
            };
            const extendableResult = createExtendableSetup(
                {
                    props,
                    context: {},
                    name: '_internal_test_component',
                },
                () => {
                    const baseValue = ref(1);
                    const title = ref('Original Title');
                    const multipliedValue = computed(() => baseValue.value * props.multiplier);
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                    const addedValue = computed(() => baseValue.value + props.added);
                    const privateValue = ref('Private');

                    return {
                        private: {
                            privateValue,
                        },
                        public: {
                            baseValue,
                            multipliedValue,
                            addedValue,
                            title,
                        },
                    };
                },
            );

            expectType<number>(extendableResult.baseValue.value);
            expectType<number>(extendableResult.multipliedValue.value);
            expectType<number>(extendableResult.addedValue.value);
            expectType<string>(extendableResult.title.value);
            expectType<string>(extendableResult.privateValue.value);
        });

        it('should have correct props types for the overrideComponentSetup', () => {
            const originalComponent = defineComponent({
                template: `
                    <div>Hello World</div>
                `,
                props: {
                    exampleString: {
                        type: String,
                        default: 'Hello',
                    },
                    exampleNumber: {
                        type: Number,
                        default: 1,
                    },
                    exampleBoolean: {
                        type: Boolean,
                        default: true,
                    },
                    exampleObject: {
                        type: Object as PropType<{
                            hello: string;
                            world: number;
                        }>,
                        default: () => ({}),
                    },
                },
                setup: (props, context) =>
                    createExtendableSetup(
                        {
                            props,
                            context,
                            name: 'originalComponent',
                        },
                        () => {
                            const count = ref(1);

                            return {
                                public: {
                                    count,
                                },
                            };
                        },
                    ),
            });

            overrideComponentSetup<typeof originalComponent>()('originalComponent', (previousState, props) => {
                // Expect the props to be correct
                expectType<number | undefined>(props.exampleNumber);
                expectType<number>(props.exampleNumber!);

                expectType<string | undefined>(props.exampleString);
                expectType<string>(props.exampleString!);

                expectType<boolean | undefined>(props.exampleBoolean);
                expectType<boolean>(props.exampleBoolean!);

                expectType<{ hello: string; world: number } | undefined>(props.exampleObject);
                expectType<{ hello: string; world: number }>(props.exampleObject!);

                return {};
            });
        });

        it('should have correct context types for the overrideComponentSetup', () => {
            const originalComponent = defineComponent({
                template: `
                    <div>Hello World</div>
                `,
                setup: (props, context) =>
                    createExtendableSetup(
                        {
                            props,
                            context,
                            name: 'originalComponent',
                        },
                        () => {
                            const count = ref(1);

                            return {
                                public: {
                                    count,
                                },
                            };
                        },
                    ),
            });

            overrideComponentSetup<typeof originalComponent>()('originalComponent', (previousState, props, context) => {
                // Expect the context to be correct typed
                expectType<Readonly<{ [name: string]: Slot | undefined }>>(context.slots);
                expectType<Record<string, unknown>>(context.attrs);
                expectType<EmitFn<unknown>>(context.emit);
                expectType<<Exposed extends Record<string, unknown> = Record<string, unknown>>(exposed?: Exposed) => void>(
                    context.expose,
                );
                expectType<SetupContext>(context);

                return {};
            });
        });
        /* eslint-enable jest/expect-expect */
    });

    /**
     * The @vue/vue3-jest plugin does not work with the
     * "vue$: '@vue/compat/dist/vue.cjs.js'" alias in the jest config.
     *
     * Therefore, we need to skip these tests for now and reactivte them
     * once compat is removed.
     *
     * If you need to run these tests remove the alias in "moduleNameMapper"
     * inside the jest config and remove global Vue registrations.
     */
    describe.skip('Script Setup usage', () => {
        it('should be able to override refs in script setup', async () => {
            const originalComponent = ExampleExtendableScriptSetupComponent;

            const wrapper = mount(originalComponent, {
                props: {
                    multiplier: 7,
                },
            });

            expect(wrapper.find('.base').text()).toContain('Base: 1');

            // Override the setup function
            overrideComponentSetup()('originalComponent', () => {
                return {
                    baseValue: ref(5),
                };
            });

            await flushPromises();

            expect(wrapper.find('.base').text()).toContain('Base: 5');
        });

        it('should be able to override reactive in script setup', async () => {
            const originalComponent = ExampleExtendableScriptSetupComponent;

            const wrapper = mount(originalComponent, {
                props: {
                    multiplier: 7,
                },
            });

            expect(wrapper.find('.deep').text()).toContain('Deep: deep');

            // Override the setup function
            overrideComponentSetup()('originalComponent', () => {
                const newReactiveValue = reactive({
                    very: {
                        deep: {
                            value: 'Hello from the override',
                        },
                    },
                });

                return {
                    reactiveValue: newReactiveValue,
                };
            });

            await flushPromises();

            expect(wrapper.find('.deep').text()).toContain('Deep: Hello from the override');
        });

        it('should be able to override computed in script setup', async () => {
            const originalComponent = ExampleExtendableScriptSetupComponent;

            const wrapper = mount(originalComponent, {
                props: {
                    multiplier: 7,
                },
            });

            expect(wrapper.find('.multiplied').text()).toContain('Multiplied: 7');

            // Override the setup function
            overrideComponentSetup()('originalComponent', (previousState) => {
                const newMultipliedValue = computed(() => previousState.multipliedValue.value * 6);

                return {
                    multipliedValue: newMultipliedValue,
                };
            });

            await flushPromises();

            expect(wrapper.find('.multiplied').text()).toContain('Multiplied: 42');
        });

        it('should be able to override methods in script setup', async () => {
            const originalComponent = ExampleExtendableScriptSetupComponent;

            const wrapper = mount(originalComponent, {
                props: {
                    multiplier: 7,
                },
            });

            expect(wrapper.find('.base').text()).toContain('Base: 1');

            // Increment the value by 1
            await wrapper.find('button.increment').trigger('click');

            expect(wrapper.find('.base').text()).toContain('Base: 2');

            // Override the setup function
            overrideComponentSetup()('originalComponent', (previousState) => {
                const newIncrement = () => {
                    // Call previous increment function and add 10 more
                    previousState.increment();
                    previousState.baseValue.value += 10;
                };

                return {
                    increment: newIncrement,
                };
            });

            await flushPromises();

            // Increment the value by 1 and then by 10
            await wrapper.find('button.increment').trigger('click');

            expect(wrapper.find('.base').text()).toContain('Base: 13');
        });

        it('should be able to access props in script setup', async () => {
            const originalComponent = ExampleExtendableScriptSetupComponent;

            const wrapper = mount(originalComponent, {
                props: {
                    multiplier: 7,
                },
            });

            expect(wrapper.find('.base').text()).toContain('Base: 1');

            // Override the setup function
            overrideComponentSetup()('originalComponent', (previousState, props) => {
                // @ts-expect-error - multiplier is defined in the original setup
                const newBaseValue = ref(props.multiplier * 10);

                return {
                    baseValue: newBaseValue,
                };
            });

            await flushPromises();

            // The base value should be 7 * 10 = 70, so it accesses the props correctly
            expect(wrapper.find('.base').text()).toContain('Base: 70');
        });

        it('should be able to access context in script setup', async () => {
            const originalComponent = ExampleExtendableScriptSetupComponent;

            const wrapper = mount(originalComponent, {
                props: {
                    multiplier: 7,
                },
                slots: {
                    header: 'Original Header',
                },
                attrs: {
                    title: 'Original Title',
                },
            });

            expect(wrapper.find('.message').text()).toContain('Message: Original message');

            // Override the setup function
            overrideComponentSetup()('originalComponent', (previousState, props, context) => {
                // Access slots
                const headerSlot = context.slots.header;

                // Access attrs
                const title = (context.attrs.title as string) ?? '';

                const newMessage = ref(`Overriden: Title: ${title}. Header slot filled: ${!!headerSlot}`);

                return {
                    message: newMessage,
                };
            });

            await flushPromises();

            expect(wrapper.find('.message').text()).toContain(
                'Message: Overriden: Title: Original Title. Header slot filled: true',
            );
        });

        it('should be able to access private values in script setup', async () => {
            const originalComponent = ExampleExtendableScriptSetupComponent;

            const wrapper = mount(originalComponent, {
                props: {
                    multiplier: 7,
                },
            });

            expect(wrapper.find('.private').text()).toContain('Private: Very private stuff');

            // Override the setup function
            overrideComponentSetup()('originalComponent', () => {
                const newPrivateStuff = ref('Overridden private stuff');

                return {
                    privateStuff: newPrivateStuff,
                };
            });

            await flushPromises();

            expect(wrapper.find('.private').text()).toContain('Private: Overridden');
        });
    });
});
