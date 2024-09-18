/**
 * @package admin
 *
 * This test is written in TS to make sure that the type inheritance
 * works correctly with the new Composition API extension system.
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-assignment, max-len, @typescript-eslint/no-unsafe-call, filename-rules/match */

import { createExtendableSetup, overrideComponentSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import { mount } from '@vue/test-utils';
import { ref, computed, reactive } from 'vue';

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
                const originalComponent = {
                    template: '<div>Count: {{ count }}</div>',
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);

                        return {
                            count: count,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.text()).toBe('Count: 1');

                // Override the setup function
                overrideComponentSetup('originalComponent', () => {
                    return {
                        count: ref(5),
                    };
                });

                await flushPromises();

                expect(wrapper.text()).toBe('Count: 5');
            });

            it('should be able to override ref values and access previous ones', async () => {
                const originalComponent = {
                    template: '<div>Count: {{ count }}</div>',
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);

                        return {
                            count: count,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.text()).toBe('Count: 1');

                // Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            count: count,
                            increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');

                // Change the count
                await wrapper.find('button').trigger('click');
                expect(wrapper.find('.count').text()).toBe('Count: 2');

                // Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: '<div>Count: {{ count }}</div>',
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);

                        return {
                            count: count,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.text()).toBe('Count: 1');

                // 1. Override the setup function
                overrideComponentSetup('originalComponent', () => {
                    return {
                        count: ref(5),
                    };
                });

                await flushPromises();

                // 2. Override the setup function
                overrideComponentSetup('originalComponent', () => {
                    return {
                        count: ref(10),
                    };
                });

                await flushPromises();

                expect(wrapper.text()).toBe('Count: 10');
            });

            it('should be able to override ref values and access previous ones (Multiple overridess)', async () => {
                const originalComponent = {
                    template: '<div>Count: {{ count }}</div>',
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);

                        return {
                            count: count,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.text()).toBe('Count: 1');

                // 1. Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
                    const oldCount = previousState.count;
                    const newCount = ref(oldCount.value + 5);

                    return {
                        count: newCount,
                    };
                });

                await flushPromises();

                // 2. Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            count: count,
                            increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');

                // Change the count
                await wrapper.find('button').trigger('click');
                expect(wrapper.find('.count').text()).toBe('Count: 2');

                // 1. Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
                    const oldCount = previousState.count;
                    const newCount = ref(oldCount.value + 5);

                    return {
                        count: newCount,
                    };
                });

                await flushPromises();

                // 2. Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
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
                            complexObject: complexObject,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Override the setup function
                overrideComponentSetup('originalComponent', () => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
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
                            complexObject: complexObject,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Listen to console.error messages
                const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});

                // Override the setup function with a invalid reactive object which doesn't have the same structure
                overrideComponentSetup('originalComponent', () => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
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
                            complexObject: complexObject,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Listen to console.error messages
                const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});

                // Override the setup function with a invalid reactive object which doesn't have the same structure
                overrideComponentSetup('originalComponent', () => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
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
                            complexObject: complexObject,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
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
                            complexObject: complexObject,
                            increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Change the count
                await wrapper.find('button').trigger('click');
                expect(wrapper.find('.count').text()).toBe('Count: 2');

                // Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
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
                            complexObject: complexObject,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // 1. Override the setup function
                overrideComponentSetup('originalComponent', () => {
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
                overrideComponentSetup('originalComponent', () => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
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
                            complexObject: complexObject,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Listen to console.error messages
                const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});

                // 1. Override the setup function with a invalid reactive object which doesn't have the same structure
                overrideComponentSetup('originalComponent', () => {
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
                overrideComponentSetup('originalComponent', () => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
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
                            complexObject: complexObject,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Listen to console.error messages
                const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});

                // 1. Override the setup function with a invalid reactive object which doesn't have the same structure
                overrideComponentSetup('originalComponent', () => {
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
                overrideComponentSetup('originalComponent', () => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
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
                            complexObject: complexObject,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // 1. Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ complexObject.count }}</div>
                        <div class="greeting-message">Greeting: {{ complexObject.greeting.message }}</div>
                        <div class="deep-message">Deep: {{ complexObject.greeting.deep.and.deeper }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
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
                            complexObject: complexObject,
                            increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.greeting-message').text()).toBe('Greeting: Hello');
                expect(wrapper.find('.deep-message').text()).toBe('Deep: Original');

                // Change the count
                await wrapper.find('button').trigger('click');
                expect(wrapper.find('.count').text()).toBe('Count: 2');

                // 1. Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const countDoubled = computed(() => count.value * 2);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            count: count,
                            countDoubled: countDoubled,
                            increment: increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');

                // Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                        <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const countDoubled = computed(() => count.value * 2);
                        const countTripled = computed(() => count.value * 3);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            count: count,
                            countDoubled: countDoubled,
                            countTripled: countTripled,
                            increment: increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 3');

                // Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                        <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const countDoubled = computed(() => count.value * 2);
                        const countTripled = computed(() => count.value * 3);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            count: count,
                            countDoubled: countDoubled,
                            countTripled: countTripled,
                            increment: increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 3');

                // Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                        <input v-model="countDoubled" type="number"/>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const countDoubled = computed({
                            get: () => count.value * 2,
                            set: (value) => {
                                count.value = value / 2;
                            },
                        });

                        return {
                            count: count,
                            countDoubled: countDoubled,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');

                // Change the countDoubled value
                await wrapper.find('input').setValue(10);
                expect(wrapper.find('.count').text()).toBe('Count: 5');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 10');

                // Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                        <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                        <input v-model="countTripled" type="number"/>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
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
                            count: count,
                            countDoubled: countDoubled,
                            countTripled: countTripled,
                        };
                    }),
                };

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
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                        <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                        <input v-model="countTripled" type="number"/>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
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
                            count: count,
                            countDoubled: countDoubled,
                            countTripled: countTripled,
                        };
                    }),
                };

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
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const countDoubled = computed(() => count.value * 2);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            count: count,
                            countDoubled: countDoubled,
                            increment: increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');

                // First override
                overrideComponentSetup('originalComponent', (previousState) => {
                    const countTripled = computed(() => previousState.count.value * 3);

                    return {
                        countDoubled: countTripled,
                    };
                });

                await flushPromises();

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 3');

                // Second override
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                    <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const countDoubled = computed(() => count.value * 2);
                        const countTripled = computed(() => count.value * 3);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            count: count,
                            countDoubled: countDoubled,
                            countTripled: countTripled,
                            increment: increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 3');

                // First override
                overrideComponentSetup('originalComponent', (previousState) => {
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
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                    <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const countDoubled = computed(() => count.value * 2);
                        const countTripled = computed(() => count.value * 3);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            count: count,
                            countDoubled: countDoubled,
                            countTripled: countTripled,
                            increment: increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 3');

                // First override
                overrideComponentSetup('originalComponent', (previousState) => {
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
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                    <input v-model="countDoubled" type="number"/>
                `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const countDoubled = computed({
                            get: () => count.value * 2,
                            set: (value) => {
                                count.value = value / 2;
                            },
                        });

                        return {
                            count: count,
                            countDoubled: countDoubled,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');

                // First override
                overrideComponentSetup('originalComponent', (previousState) => {
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
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                    <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                    <input v-model="countTripled" type="number"/>
                `,
                    setup: createExtendableSetup('originalComponent', () => {
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
                            count: count,
                            countDoubled: countDoubled,
                            countTripled: countTripled,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 3');

                // First override
                overrideComponentSetup('originalComponent', (previousState) => {
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
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="count-doubled">Count Doubled: {{ countDoubled }}</div>
                    <div class="count-tripled">Count Tripled: {{ countTripled }}</div>
                    <input v-model="countTripled" type="number"/>
                `,
                    setup: createExtendableSetup('originalComponent', () => {
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
                            count: count,
                            countDoubled: countDoubled,
                            countTripled: countTripled,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);

                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.count-doubled').text()).toBe('Count Doubled: 2');
                expect(wrapper.find('.count-tripled').text()).toBe('Count Tripled: 3');

                // First override
                overrideComponentSetup('originalComponent', (previousState) => {
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
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            count: count,
                            increment: increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');

                // Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            count: count,
                            increment: increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');

                // Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            count: count,
                            increment: increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');

                // First override
                overrideComponentSetup('originalComponent', (previousState) => {
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
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                        <div class="count">Count: {{ count }}</div>
                        <button @click="increment">Increment</button>
                    `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            count: count,
                            increment: increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');

                // 1. Override the setup function
                overrideComponentSetup('originalComponent', (previousState) => {
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
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="double-count">Double Count: {{ doubleCount }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const doubleCount = ref(2);
                        const increment = () => {
                            count.value += 1;
                            doubleCount.value = count.value * 2;
                        };

                        return {
                            count: count,
                            doubleCount: doubleCount,
                            increment: increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.double-count').text()).toBe('Double Count: 2');

                // First override
                overrideComponentSetup('originalComponent', (previousState) => {
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
                overrideComponentSetup('originalComponent', (previousState) => {
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
                const originalComponent = {
                    template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="double-count">Double Count: {{ doubleCount }}</div>
                    <button @click="increment">Increment</button>
                `,
                    setup: createExtendableSetup('originalComponent', () => {
                        const count = ref(1);
                        const doubleCount = ref(2);
                        const increment = () => {
                            count.value += 1;
                            doubleCount.value = count.value * 2;
                        };

                        return {
                            count: count,
                            doubleCount: doubleCount,
                            increment: increment,
                        };
                    }),
                };

                const wrapper = mount(originalComponent);
                expect(wrapper.find('.count').text()).toBe('Count: 1');
                expect(wrapper.find('.double-count').text()).toBe('Double Count: 2');

                // First override
                overrideComponentSetup('originalComponent', (previousState) => {
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
                overrideComponentSetup('originalComponent', (previousState) => {
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
            const originalComponent = {
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
                setup: createExtendableSetup('originalComponent', (props) => {
                    const count = ref(1);
                    const multipliedCount = computed(() => count.value * props.multiplier);

                    return {
                        count,
                        multipliedCount,
                    };
                }),
            };

            const wrapper = mount(originalComponent, {
                props: {
                    multiplier: 2,
                },
            });

            expect(wrapper.find('.count').text()).toBe('Count: 1');
            expect(wrapper.find('.multiplier').text()).toBe('Multiplier: 2');
            expect(wrapper.find('.multiplied').text()).toBe('Multiplied: 2');

            overrideComponentSetup('originalComponent', (previousState, props) => {
                const newCount = ref(5);
                // Multiply by the multiplier prop and then multiply by 2
                const newMultipliedCount = computed(() => newCount.value * props.multiplier * 2);

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
            const originalComponent = {
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
                setup: createExtendableSetup('originalComponent', (props) => {
                    const count = ref(1);
                    const multipliedCount = computed(() => count.value * props.multiplier);

                    return {
                        count,
                        multipliedCount,
                    };
                }),
            };

            const wrapper = mount(originalComponent, {
                props: {
                    multiplier: 2,
                },
            });

            expect(wrapper.find('.count').text()).toBe('Count: 1');
            expect(wrapper.find('.multiplier').text()).toBe('Multiplier: 2');
            expect(wrapper.find('.multiplied').text()).toBe('Multiplied: 2');

            overrideComponentSetup('originalComponent', (previousState, props) => {
                const newCount = ref(5);
                // Multiply by the multiplier prop and then multiply by 2
                const newMultipliedCount = computed(() => newCount.value * props.multiplier * 2);

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
            const originalComponent = {
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
                setup: createExtendableSetup('originalComponent', (props) => {
                    const baseValue = ref(1);
                    const multipliedValue = computed(() => baseValue.value * props.multiplier);
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                    const addedValue = computed(() => baseValue.value + props.added);

                    return {
                        baseValue,
                        multipliedValue,
                        addedValue,
                    };
                }),
            };

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

            overrideComponentSetup('originalComponent', (previousState, props) => {
                const newBaseValue = ref(5);
                const newMultipliedValue = computed(() => newBaseValue.value * props.multiplier);

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

            overrideComponentSetup('originalComponent', (previousState, props) => {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                const newAddedValue = computed(() => previousState.baseValue.value + props.added * 2);

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
            const originalComponent = {
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
                setup: createExtendableSetup('originalComponent', (props) => {
                    const count = ref(1);
                    const multipliedCount = computed(() => count.value * props.multiplier);

                    return {
                        count,
                        multipliedCount,
                        // This is not allowed and should cause an error
                        multiplier: props.multiplier,
                    };
                }),
            };

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
            const originalComponent = {
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
                setup: createExtendableSetup('originalComponent', (props) => {
                    const count = ref(1);
                    const multipliedCount = computed(() => count.value * props.multiplier);

                    return {
                        count,
                        multipliedCount,
                    };
                }),
            };

            mount(originalComponent, {
                props: {
                    multiplier: 2,
                },
            });

            // Mock console.error
            const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});

            // Override the setup function
            overrideComponentSetup('originalComponent', (previousState, props) => {
                const newCount = ref(5);
                // Multiply by the multiplier prop and then multiply by 2
                const newMultipliedCount = computed(() => newCount.value * props.multiplier * 2);

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
            const originalComponent = {
                template: `
                    <div>
                        <slot name="header">
                            {{ message }}
                        </slot>
                        {{ secondMessage }}
                    </div>
                `,
                setup: createExtendableSetup('originalComponent', () => {
                    const message = ref('Original message');
                    const secondMessage = ref('Original second message');

                    return {
                        message,
                        secondMessage,
                    };
                }),
            };

            const wrapper = mount(originalComponent, {
                slots: {
                    header: 'Original Header',
                },
                attrs: {
                    title: 'Original Title',
                },
            });

            expect(wrapper.text()).toBe('Original Header Original second message');

            overrideComponentSetup('originalComponent', (previousState, props, context) => {
                // Access slots
                const headerSlot = context.slots.header;

                // Access attrs
                const title = context.attrs.title;

                const newSecondMessage = ref(`Overriden: Title: ${title}. Header slot filled: ${!!headerSlot}`);

                return {
                    secondMessage: newSecondMessage,
                };
            });

            await flushPromises();

            expect(wrapper.text()).toBe('Original Header Overriden: Title: Original Title. Header slot filled: true');
        });

        it('should be able to access context in the override (with empty slot)', async () => {
            const originalComponent = {
                template: `
                <div>
                    <slot name="header">
                        {{ message }}
                    </slot>
                    {{ secondMessage }}
                </div>
            `,
                setup: createExtendableSetup('originalComponent', () => {
                    const message = ref('Original message');
                    const secondMessage = ref('Original second message');

                    return {
                        message,
                        secondMessage,
                    };
                }),
            };

            const wrapper = mount(originalComponent, {
                attrs: {
                    title: 'Original Title',
                },
            });

            expect(wrapper.text()).toBe('Original message Original second message');

            overrideComponentSetup('originalComponent', (previousState, props, context) => {
                // Access slots
                const headerSlot = context.slots.header;

                // Access attrs
                const title = context.attrs.title;

                const newSecondMessage = ref(`Overriden: Title: ${title}. Header slot filled: ${!!headerSlot}`);

                return {
                    secondMessage: newSecondMessage,
                };
            });

            await flushPromises();

            expect(wrapper.text()).toBe('Original message Overriden: Title: Original Title. Header slot filled: false');
        });

        it('should be able to modify exposed properties using context.expose', async () => {
            const originalComponent = {
                template: '<div>{{ exposedValue }}</div>',
                setup: createExtendableSetup('originalComponent', (props, context) => {
                    const exposedValue = ref('Original');
                    context.expose({ exposedValue });

                    return {
                        exposedValue,
                    };
                }),
            };

            const wrapper = mount(originalComponent);

            overrideComponentSetup('originalComponent', () => {
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
});
