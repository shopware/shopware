/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import ComponentFactory from 'src/core/factory/async-component.factory';
import TemplateFactory from 'src/core/factory/template.factory';
import { cloneDeep } from 'src/core/service/utils/object.utils';


function createComponentMatrix(components) {
    const possibilities = [
        (value) => value,
        (value) => (v) => Promise.resolve(value(v)),
    ];

    const possibilitiesForComponents = Object.entries(components).map(([key, value]) => {
        return possibilities.map(possibility => {
            return {
                key: key,
                value: possibility(value),
            };
        });
    });

    // create cartesian product of all component possibilities
    const flatten = (arr) => [].concat(...arr);
    const cartesianProduct = (sets) => {
        return sets.reduce(
            (acc, set) => {
                return flatten(acc.map(x => set.map(y => [...x, y])));
            },
            [[]],
        );
    };

    const result = cartesianProduct(possibilitiesForComponents).map((product) => {
        return product.reduce((acc, { key, value }) => {
            acc[key] = value;
            return acc;
        }, {});
    });

    return result.map((resultCase) => {
        const testCase = Object.values(resultCase).reduce((acc, componentVariant) => {
            if (componentVariant() instanceof Promise) {
                acc += 'ASYNC ';
            } else {
                acc += 'SYNC ';
            }
            return acc;
        }, '');

        return {
            testCase,
            components: resultCase,
        };
    });
}

expect.extend({
    methodReturnsPromise(received) {
        if (
            typeof received === 'function' &&
            received() instanceof Promise
        ) {
            return {
                pass: true,
                message: () => `expected ${received} returns a Promise`,
            };
        }

        return {
            pass: false,
            message: () => `expected ${received} does not return a Promise`,
        };
    },
    methodReturnsNoPromise(received) {
        if (
            typeof received === 'function' &&
            received() instanceof Promise
        ) {
            return {
                pass: false,
                message: () => `expected ${received} returns a Promise`,
            };
        }

        return {
            pass: true,
            message: () => `expected ${received} does not return a Promise`,
        };
    },
});

describe('core/factory/async-component.factory.ts', () => {
    beforeEach(async () => {
        ComponentFactory.getComponentRegistry().clear();
        ComponentFactory.getOverrideRegistry().clear();
        ComponentFactory._clearComponentHelper();
        TemplateFactory.getTemplateRegistry().clear();
        TemplateFactory.getNormalizedTemplateRegistry().clear();
        TemplateFactory.disableTwigCache();
        ComponentFactory.markComponentTemplatesAsNotResolved();
    });

    it('test the component matrix', async () => {
        const twoMatrix = createComponentMatrix({
            A: () => 1,
            B: () => 2,
        });
        expect(twoMatrix).toHaveLength(4);
        expect(twoMatrix).toEqual([
            {
                components: {
                    A: expect.methodReturnsNoPromise(),
                    B: expect.methodReturnsNoPromise(),
                },
                testCase: 'SYNC SYNC ',
            },
            {
                components: {
                    A: expect.methodReturnsNoPromise(),
                    B: expect.methodReturnsPromise(),
                },
                testCase: 'SYNC ASYNC ',
            },
            {
                components: {
                    A: expect.methodReturnsPromise(),
                    B: expect.methodReturnsNoPromise(),
                },
                testCase: 'ASYNC SYNC ',
            },
            {
                components: {
                    A: expect.methodReturnsPromise(),
                    B: expect.methodReturnsPromise(),
                },
                testCase: 'ASYNC ASYNC ',
            },
        ]);

        const threeMatrix = createComponentMatrix({
            A: () => 1,
            B: () => 2,
            C: () => 3,
        });
        expect(threeMatrix).toHaveLength(8);
        expect(threeMatrix).toEqual([
            {
                components: {
                    A: expect.methodReturnsNoPromise(),
                    B: expect.methodReturnsNoPromise(),
                    C: expect.methodReturnsNoPromise(),
                },
                testCase: 'SYNC SYNC SYNC ',
            },
            {
                components: {
                    A: expect.methodReturnsNoPromise(),
                    B: expect.methodReturnsNoPromise(),
                    C: expect.methodReturnsPromise(),
                },
                testCase: 'SYNC SYNC ASYNC ',
            },
            {
                components: {
                    A: expect.methodReturnsNoPromise(),
                    B: expect.methodReturnsPromise(),
                    C: expect.methodReturnsNoPromise(),
                },
                testCase: 'SYNC ASYNC SYNC ',
            },
            {
                components: {
                    A: expect.methodReturnsNoPromise(),
                    B: expect.methodReturnsPromise(),
                    C: expect.methodReturnsPromise(),
                },
                testCase: 'SYNC ASYNC ASYNC ',
            },
            {
                components: {
                    A: expect.methodReturnsPromise(),
                    B: expect.methodReturnsNoPromise(),
                    C: expect.methodReturnsNoPromise(),
                },
                testCase: 'ASYNC SYNC SYNC ',
            },
            {
                components: {
                    A: expect.methodReturnsPromise(),
                    B: expect.methodReturnsNoPromise(),
                    C: expect.methodReturnsPromise(),
                },
                testCase: 'ASYNC SYNC ASYNC ',
            },
            {
                components: {
                    A: expect.methodReturnsPromise(),
                    B: expect.methodReturnsPromise(),
                    C: expect.methodReturnsNoPromise(),
                },
                testCase: 'ASYNC ASYNC SYNC ',
            },
            {
                components: {
                    A: expect.methodReturnsPromise(),
                    B: expect.methodReturnsPromise(),
                    C: expect.methodReturnsPromise(),
                },
                testCase: 'ASYNC ASYNC ASYNC ',
            },
        ]);
    });

    describe('should register a component and it should be registered in the component registry', () => {
        createComponentMatrix({
            A: () => ({ template: '<div>This is a test template.</div>' }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                const component = ComponentFactory.register('test-component', components.A());

                const registry = ComponentFactory.getComponentRegistry();
                expect(typeof component).toBe('function');
                expect(registry.has('test-component')).toBe(true);
                expect(typeof registry.get('test-component')).toBe('function');
            });
        });
    });

    describe('should not be possible to register a component with the same name twice', () => {
        createComponentMatrix({
            A: () => ({ template: '<div>This is a test template.</div>' }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                const spy = jest.spyOn(console, 'warn').mockImplementation();
                const compDefinition = ComponentFactory.register('test-component', components.A());
                const component = ComponentFactory.register('test-component', compDefinition);

                expect(component).toBe(false);
                expect(spy).toHaveBeenCalledWith(
                    '[ComponentFactory]',
                    'The component "test-component" is already registered. Please select a unique name for your component.',
                    expect.any(Function),
                );
            });
        });
    });

    describe('should not be possible to register a component without a name', () => {
        createComponentMatrix({
            A: () => ({ template: '<div>This is a test template.</div>' }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                const spy = jest.spyOn(console, 'warn').mockImplementation();
                const component = ComponentFactory.register('', components.A());

                expect(component).toBe(false);
                expect(spy).toHaveBeenCalledWith(
                    '[ComponentFactory]',
                    'A component always needs a name.',
                    expect.anything(),
                );
            });
        });
    });

    describe('should not be possible to register a component without a template', () => {
        createComponentMatrix({
            A: () => ({}),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                const spy = jest.spyOn(console, 'warn').mockImplementation();
                const component = await ComponentFactory.register('test-component', components.A())();

                expect(component).toBe(false);
                expect(spy).toHaveBeenCalledWith(
                    '[ComponentFactory]',
                    'The component "test-component" needs a template to be functional.',
                    'Please add a "template" property to your component definition',
                    expect.anything(),
                );
            });
        });
    });

    describe('should not have a template property after registering a component', () => {
        createComponentMatrix({
            A: () => ({
                template: '<div>This is a test template.</div>',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                const component = ComponentFactory.register('test-component', components.A());

                expect(component.template).toBeUndefined();
            });
        });
    });

    describe('should extend a given component & should register a new component (without template)', () => {
        createComponentMatrix({
            A: () => ({
                created() {},
                template: '<div>This is a test template.</div>',
            }),
            B: () => ({
                updated() {},
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());

                const extension = await ComponentFactory.extend('test-component-extension', 'test-component', components.B())();
                const registry = ComponentFactory.getComponentRegistry();

                expect(typeof extension.updated).toBe('function');
                expect(typeof extension.extends).toBe('string');
                expect(extension.extends).toBe('test-component');
                expect(registry.has('test-component-extension')).toBe(true);
                expect(typeof registry.get('test-component-extension')).toBe('function');
            });
        });
    });

    describe('should extend a given component & should register a new component (with template)', () => {
        createComponentMatrix({
            A: () => ({
                created() {},
                template: '<div>This is a test template.</div>',
            }),
            B: () => ({
                updated() {},
                template: '<div>This is an extension.</div>',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());

                const extension = await ComponentFactory.extend('test-component-extension', 'test-component', components.B())();
                const registry = ComponentFactory.getComponentRegistry();

                expect(typeof extension.updated).toBe('function');
                expect(typeof extension.extends).toBe('string');
                expect(extension.extends).toBe('test-component');
                expect(registry.has('test-component-extension')).toBe(true);
                expect(typeof (await registry.get('test-component-extension')())).toBe('object');
                expect(extension.template).toBeUndefined();
            });
        });
    });

    describe('should register an override of an existing component in the override registry (without index)', () => {
        createComponentMatrix({
            A: () => ({
                created() {},
                methods: {
                    testMethod() {
                        return 'This is a test method.';
                    },
                },
                template: '<div>This is a test template.</div>',
            }),
            B: () => ({
                methods: {
                    testMethod() {
                        return 'This is an override.';
                    },
                },
                template: '<div>This is an override.</div>',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                const override = ComponentFactory.override('test-component', components.B());

                const registry = ComponentFactory.getComponentRegistry();
                const overrideRegistry = ComponentFactory.getOverrideRegistry();

                expect(typeof (await override()).methods.testMethod).toBe('function');
                expect(override.template).toBeUndefined();
                expect(registry.has('test-component')).toBe(true);
                expect(typeof registry.get('test-component')).toBe('function');
                expect(overrideRegistry.has('test-component')).toBe(true);
                expect(overrideRegistry.get('test-component')).toBeInstanceOf(Array);
                expect(overrideRegistry.get('test-component')).toHaveLength(1);
                expect(overrideRegistry.get('test-component')[0]).toBeInstanceOf(Object);
            });
        });
    });

    describe('should register two overrides of an existing component in the override registry (with index)', () => {
        createComponentMatrix({
            A: () => ({
                created() {},
                methods: {
                    testMethod() {
                        return 'This is a test method.';
                    },
                },
                template: '<div>This is a test template.</div>',
            }),
            B: () => ({
                methods: {
                    testMethod() {
                        return 'This is the first override.';
                    },
                },
            }),
            C: () => ({
                methods: {
                    testMethod() {
                        return 'This is the second override.';
                    },
                },
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                const overrideOne = ComponentFactory.override('test-component', components.B());
                const overrideTwo = ComponentFactory.override('test-component', components.C(), 0);

                const registry = ComponentFactory.getComponentRegistry();
                const overrideRegistry = ComponentFactory.getOverrideRegistry();

                expect(typeof (await overrideOne()).methods.testMethod).toBe('function');
                expect(typeof (await overrideTwo()).methods.testMethod).toBe('function');
                expect(overrideOne.template).toBeUndefined();
                expect(overrideTwo.template).toBeUndefined();
                expect(registry.has('test-component')).toBe(true);
                expect(registry.get('test-component')).toBeInstanceOf(Object);
                expect(overrideRegistry.has('test-component')).toBe(true);
                expect(overrideRegistry.get('test-component')).toBeInstanceOf(Array);
                expect(overrideRegistry.get('test-component')).toHaveLength(2);
                expect(overrideRegistry.get('test-component')[0]).toBeInstanceOf(Object);
                expect(overrideRegistry.get('test-component')[1]).toBeInstanceOf(Object);
                expect(typeof (await overrideRegistry.get('test-component')[0]()).methods.testMethod).toBe('function');
                expect(typeof (await overrideRegistry.get('test-component')[1]()).methods.testMethod).toBe('function');
                expect((await overrideRegistry.get('test-component')[0]()).methods.testMethod()).toBe('This is the second override.');
                expect((await overrideRegistry.get('test-component')[1]()).methods.testMethod()).toBe('This is the first override.');
            });
        });
    });

    describe('should provide the rendered template of a component including overrides', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block content %}<div>This is a test template.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block content %}<div>This is a template override.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.override('test-component', components.B());

                const overriddenTemplate = await ComponentFactory.getComponentTemplate('test-component');
                expect(overriddenTemplate).toBe('<div>This is a template override.</div>');
            });
        });
    });

    describe('should extend a block within a component', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block content %}<div>This is the {% block name %}base{% endblock %} component</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block name %}extended{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.extend('test-component-extension', 'test-component', components.B());

                const renderedTemplate = await ComponentFactory.getComponentTemplate('test-component');
                const extendedTemplate = await ComponentFactory.getComponentTemplate('test-component-extension');

                expect(renderedTemplate).toBe('<div>This is the base component</div>');
                expect(extendedTemplate).toBe('<div>This is the extended component</div>');
            });
        });
    });

    describe('should be able to extend a component before itself was registered', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block base %}<div>This is a template override.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block base %}<div>This is a test template.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.extend('test-component-extension', 'test-component', components.A());
                ComponentFactory.register('test-component', components.B());

                const renderedTemplate = await ComponentFactory.getComponentTemplate('test-component');
                const extendedTemplate = await ComponentFactory.getComponentTemplate('test-component-extension');

                expect(renderedTemplate).toBe('<div>This is a test template.</div>');
                expect(extendedTemplate).toBe('<div>This is a template override.</div>');
            });
        });
    });

    describe('should be able to extend a component with blocks before itself was registered', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block content %}<div>This is a template override.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block content %}<div>This is a test template.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.extend('test-component-extension', 'test-component', components.A());

                ComponentFactory.register('test-component', components.B());

                const renderedTemplate = await ComponentFactory.getComponentTemplate('test-component');
                const extendedTemplate = await ComponentFactory.getComponentTemplate('test-component-extension');

                expect(renderedTemplate).toBe('<div>This is a test template.</div>');
                expect(extendedTemplate).toBe('<div>This is a template override.</div>');
            });
        });
    });

    describe('should be able to override a component before itself was registered', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block content %}<div>This is a template override.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block content %}<div>This is a test template.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.override('test-component', components.A());

                ComponentFactory.register('test-component', components.B());

                const template = await ComponentFactory.getComponentTemplate('test-component');
                expect(template).toBe('<div>This is a template override.</div>');
            });
        });
    });

    describe('should ignore overrides if block does not exists', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block name %}<div>This is a template override.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block content %}<div>This is a test template.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.override('test-component', components.A());

                ComponentFactory.register('test-component', components.B());

                const overriddenTemplate = await ComponentFactory.getComponentTemplate('test-component');

                expect(overriddenTemplate).toBe('<div>This is a test template.</div>');
            });
        });
    });

    describe('should ignore overrides if override has no blocks', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block name %}<div>This is a template override.</div>{% endblock %}',
            }),
            B: () => ({
                template: '<div>This is a test template.</div>',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.override('test-component', components.A());

                ComponentFactory.register('test-component', components.B());

                const overriddenTemplate = await ComponentFactory.getComponentTemplate('test-component');

                expect(overriddenTemplate).toBe('<div>This is a test template.</div>');
            });
        });
    });

    describe('should build the final component structure without extending', () => {
        createComponentMatrix({
            A: () => ({
                created() {},
                methods: {
                    testMethod() {
                        return 'This is a test method.';
                    },
                },
                template: '{% block content %}<div>This is a test template.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());

                const component = await ComponentFactory.build('test-component');

                expect(component).toBeInstanceOf(Object);
                expect(component.methods).toBeInstanceOf(Object);
                expect(typeof component.methods.testMethod).toBe('function');
                expect(component.methods.testMethod()).toBe('This is a test method.');
                expect(component.template).toBe('<div>This is a test template.</div>');
            });
        });
    });

    describe('should build the final component structure with extension', () => {
        createComponentMatrix({
            A: () => ({
                created() {},
                methods: {
                    testMethod() {
                        return 'This is a test method.';
                    },
                },
                template: '{% block content %}<div>This is a test template.</div>{% endblock %}',
            }),
            B: () => ({
                methods: {
                    testMethod() {
                        return 'This is an extension.';
                    },
                },
                template: '{% block content %}<div>This is an extended template.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.extend('test-component-extension', 'test-component', components.B());

                const component = await ComponentFactory.build('test-component');
                const extension = await ComponentFactory.build('test-component-extension');

                expect(component).toBeInstanceOf(Object);
                expect(component.methods).toBeInstanceOf(Object);
                expect(typeof component.methods.testMethod).toBe('function');
                expect(component.methods.testMethod()).toBe('This is a test method.');
                expect(component.template).toBe('<div>This is a test template.</div>');

                expect(extension).toBeInstanceOf(Object);
                expect(extension.methods).toBeInstanceOf(Object);
                expect(typeof extension.methods.testMethod).toBe('function');
                expect(extension.methods.testMethod()).toBe('This is an extension.');
                expect(extension.template).toBe('<div>This is an extended template.</div>');

                expect(extension.extends).toBeInstanceOf(Object);
                expect(extension.extends.template).toBeUndefined();
                expect(extension.extends.methods).toBeInstanceOf(Object);
                expect(typeof extension.extends.methods.testMethod).toBe('function');
                expect(extension.extends.methods.testMethod()).toBe('This is a test method.');
            });
        });
    });


    describe('should build multiple extended component with parent template', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block content %}<div>This is a test template.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block content %}<div>{% parent %}I am a child.</div>{% endblock %}',
            }),
            C: () => ({
                template: '{% block content %}<div>{% parent %}I am a grandchild.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.extend('test-component-child', 'test-component', components.B());
                ComponentFactory.extend('test-component-grandchild', 'test-component-child', components.C());

                const base = await ComponentFactory.build('test-component');
                const child = await ComponentFactory.build('test-component-child');
                const grandchild = await ComponentFactory.build('test-component-grandchild');

                expect(base.template).toBe('<div>This is a test template.</div>');
                expect(child.template).toBe('<div><div>This is a test template.</div>I am a child.</div>');

                // eslint-disable-next-line max-len
                expect(grandchild.template).toBe('<div><div><div>This is a test template.</div>I am a child.</div>I am a grandchild.</div>');
            });
        });
    });

    describe('should build the final component structure with an override', () => {
        createComponentMatrix({
            A: () => ({
                created() {},
                methods: {
                    testMethod() {
                        return 'This is a test method.';
                    },
                },
                template: '{% block content %}<div>This is a test template.</div>{% endblock %}',
            }),
            B: () => ({
                methods: {
                    testMethod() {
                        return 'This is an override.';
                    },
                },
                template: '{% block content %}<div>This is an override of a template.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.override('test-component', components.B());

                const component = await ComponentFactory.build('test-component');

                expect(component).toBeInstanceOf(Object);
                expect(component.methods).toBeInstanceOf(Object);
                expect(typeof component.methods.testMethod).toBe('function');
                expect(component.methods.testMethod()).toBe('This is an override.');
                expect(component.template).toBe('<div>This is an override of a template.</div>');

                expect(component.extends).toBeInstanceOf(Object);
                expect(component.extends.template).toBeUndefined();
                expect(component.extends.methods).toBeInstanceOf(Object);
                expect(typeof component.extends.methods.testMethod).toBe('function');
                expect(component.extends.methods.testMethod()).toBe('This is a test method.');
            });
        });
    });

    describe('should build the final component structure with an override with parent', () => {
        createComponentMatrix({
            A: () => ({
                created() {},
                methods: {
                    testMethod() {
                        return 'This is a test method.';
                    },
                },
                template: '{% block content %}<div>This is a test template.</div>{% endblock %}',
            }),
            B: () => ({
                methods: {
                    testMethod() {
                        return 'This is an override.';
                    },
                },
                template: '{% block content %}<div>{% parent %}This is an override of a template.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.override('test-component', components.B());

                const component = await ComponentFactory.build('test-component');

                expect(component).toBeInstanceOf(Object);
                expect(component.methods).toBeInstanceOf(Object);
                expect(typeof component.methods.testMethod).toBe('function');
                expect(component.methods.testMethod()).toBe('This is an override.');
                expect(component.template).toBe('<div><div>This is a test template.</div>This is an override of a template.</div>');

                expect(component.extends).toBeInstanceOf(Object);
                expect(component.extends.template).toBeUndefined();
                expect(component.extends.methods).toBeInstanceOf(Object);
                expect(typeof component.extends.methods.testMethod).toBe('function');
                expect(component.extends.methods.testMethod()).toBe('This is a test method.');
            });
        });
    });

    describe('should build the final component structure with multiple overrides', () => {
        createComponentMatrix({
            A: () => ({
                created() {},
                methods: {
                    singleOverride() {
                        return 'This method should be overridden once.';
                    },
                    doubleOverride() {
                        return 'This method should be overridden twice.';
                    },
                },
                template: '{% block content %}<div>This is a test template.</div>{% endblock %}',
            }),
            B: () => ({
                methods: {
                    singleOverride() {
                        return 'This is the first override.';
                    },
                    doubleOverride() {
                        return 'This is the first override.';
                    },
                },
                template: '{% block content %}<div>{% parent %}This is an override of a template.</div>{% endblock %}',
            }),
            C: () => ({
                methods: {
                    doubleOverride() {
                        return 'This is the second override.';
                    },
                },
                // eslint-disable-next-line max-len
                template: '{% block content %}<div>{% parent %}This is an override of an overridden template.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.override('test-component', components.B());

                const componentAfterFirstOverride = await ComponentFactory.build('test-component');
                ComponentFactory.markComponentTemplatesAsNotResolved();

                ComponentFactory.override('test-component', components.C());

                const componentAfterSecondOverride = await ComponentFactory.build('test-component');

                expect(componentAfterFirstOverride).toBeInstanceOf(Object);
                expect(componentAfterFirstOverride.methods).toBeInstanceOf(Object);
                expect(typeof componentAfterFirstOverride.methods.doubleOverride).toBe('function');
                expect(componentAfterFirstOverride.methods.doubleOverride()).toBe('This is the first override.');
                // eslint-disable-next-line max-len
                expect(componentAfterFirstOverride.template).toBe('<div><div>This is a test template.</div>This is an override of a template.</div>');

                expect(componentAfterSecondOverride).toBeInstanceOf(Object);
                expect(componentAfterSecondOverride.methods).toBeInstanceOf(Object);
                expect(typeof componentAfterSecondOverride.methods.doubleOverride).toBe('function');
                expect(componentAfterSecondOverride.methods.doubleOverride()).toBe('This is the second override.');
                // eslint-disable-next-line max-len
                expect(componentAfterSecondOverride.template).toBe('<div><div><div>This is a test template.</div>This is an override of a template.</div>This is an override of an overridden template.</div>');

                expect(componentAfterSecondOverride.extends).toBeInstanceOf(Object);
                expect(componentAfterSecondOverride.extends.template).toBeUndefined();
                expect(componentAfterSecondOverride.extends.methods).toBeInstanceOf(Object);
            });
        });
    });

    describe('should build the final component structure with an extend and super-call', () => {
        createComponentMatrix({
            A: () => ({
                methods: {
                    testMethod() {
                        return 'This is a test method.';
                    },
                },
                template: '<div>test-component</div>',
            }),
            B: () => ({
                methods: {
                    testMethod() {
                        const prev = this.$super('testMethod');

                        return `This is an override. ${prev}`;
                    },
                },
                template: '<div>extended-component</div>',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.extend('extended-component', 'test-component', components.B());

                const component = shallowMount(await ComponentFactory.build('extended-component'));

                expect(component.vm).toBeTruthy();
                expect(component.vm.testMethod()).toBe('This is an override. This is a test method.');
            });
        });
    });

    describe('should build the final component structure with an override and super-call', () => {
        createComponentMatrix({
            A: () => ({
                methods: {
                    testMethod() {
                        return 'This is a test method.';
                    },
                },
                template: '<div>test-component</div>',
            }),
            B: () => ({
                methods: {
                    testMethod() {
                        const prev = this.$super('testMethod');

                        return `This is an override. ${prev}`;
                    },
                },
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.override('test-component', components.B());

                const component = shallowMount(await ComponentFactory.build('test-component'));

                expect(component.vm).toBeTruthy();
                expect(component.vm.testMethod()).toBe('This is an override. This is a test method.');
            });
        });
    });

    describe('should build the final component structure with an overriden override and super-call', () => {
        createComponentMatrix({
            A: () => ({
                methods: {
                    testMethod() {
                        return 'This is a test method.';
                    },
                },
                template: '<div>test-component</div>',
            }),
            B: () => ({
                methods: {
                    testMethod() {
                        const prev = this.$super('testMethod');

                        return `This is an override. ${prev}`;
                    },
                },
            }),
            C: () => ({
                methods: {
                    testMethod() {
                        const prev = this.$super('testMethod');

                        return `This is an overridden override. ${prev}`;
                    },
                },
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.override('test-component', components.B());
                ComponentFactory.override('test-component', components.C());

                const component = shallowMount(await ComponentFactory.build('test-component'));

                expect(component.vm).toBeTruthy();
                expect(component.vm.testMethod())
                    .toBe('This is an overridden override. This is an override. This is a test method.');
            });
        });
    });

    describe('should build the final component structure with an extension, an override and super-calls', () => {
        createComponentMatrix({
            A: () => ({
                methods: {
                    testMethod() {
                        return 'This is the original method.';
                    },
                },
                template: '<div>test-component</div>',
            }),
            B: () => ({
                methods: {
                    testMethod() {
                        const prev = this.$super('testMethod');

                        return `This is an override. ${prev}`;
                    },
                },
            }),
            C: () => ({
                methods: {
                    testMethod() {
                        const prev = this.$super('testMethod');

                        return `This is an extension. ${prev}`;
                    },
                },
                template: '<div>extended-component</div>',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.override('test-component', components.B());
                ComponentFactory.extend('extended-test-component', 'test-component', components.C());

                const component = shallowMount(await ComponentFactory.build('extended-test-component'));

                expect(component.vm).toBeTruthy();
                expect(component.vm.testMethod())
                    .toBe('This is an extension. This is an override. This is the original method.');
            });
        });
    });

    describe('should build the final component structure with an extended extension, an overriden override and super-calls', () => {
        createComponentMatrix({
            A: () => ({
                methods: {
                    testMethod() {
                        return 'This is the original method.';
                    },
                },
                template: '<div>test-component</div>',
            }),
            B: () => ({
                methods: {
                    testMethod() {
                        const prev = this.$super('testMethod');

                        return `This is an override 1. ${prev}`;
                    },
                },
            }),
            C: () => ({
                methods: {
                    testMethod() {
                        const prev = this.$super('testMethod');

                        return `This is an override 2. ${prev}`;
                    },
                },
            }),
            D: () => ({
                methods: {
                    testMethod() {
                        const prev = this.$super('testMethod');

                        return `This is an extension 1. ${prev}`;
                    },
                },
                template: '<div>extended-component</div>',
            }),
            E: () => ({
                methods: {
                    testMethod() {
                        const prev = this.$super('testMethod');

                        return `This is an extension 2. ${prev}`;
                    },
                },
                template: '<div>extended-component</div>',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.override('test-component', components.B());
                ComponentFactory.override('test-component', components.C());
                ComponentFactory.extend('extended-test-component', 'test-component', components.D());
                ComponentFactory.extend('extended-extended-test-component', 'extended-test-component', components.E());

                const extensionComponent = shallowMount(await ComponentFactory.build('extended-extended-test-component'));

                expect(extensionComponent.vm).toBeTruthy();
                expect(extensionComponent.vm.testMethod())
                    .toBe(
                        'This is an extension 2. This is an extension 1. This is an override 2. This is an override 1. '
                        + 'This is the original method.',
                    );
            });
        });
    });

    describe('should build the final component structure with multiple inheritance and super-call', () => {
        createComponentMatrix({
            A: () => ({
                methods: {
                    testMethod() {
                        return 'This is a test method.';
                    },
                },
                template: '<div>test-component</div>',
            }),
            B: () => ({
                methods: {
                    testMethod() {
                        const prev = this.$super('testMethod');

                        return `This is an extension. ${prev}`;
                    },
                },
                template: '<div>extension-1</div>',
            }),
            C: () => ({
                methods: {
                    testMethod() {
                        const prev = this.$super('testMethod');

                        return `This is an extended extension. ${prev}`;
                    },
                },
                template: '<div>extension-2</div>',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.extend('extension-1', 'test-component', components.B());
                ComponentFactory.extend('extension-2', 'extension-1', components.C());

                const component = shallowMount(await ComponentFactory.build('extension-2'));

                expect(component.vm).toBeTruthy();
                expect(component.vm.testMethod())
                    .toBe('This is an extended extension. This is an extension. This is a test method.');
            });
        });
    });

    describe('should build the final component structure extending a component with computed properties', () => {
        createComponentMatrix({
            A: () => ({
                computed: {
                    fooBar() {
                        return 'fooBar';
                    },
                    getterSetter: {
                        get() {
                            return this._getterSetter;
                        },
                        set(value) {
                            this._getterSetter = value;
                        },
                    },
                },
                template: '<div>test-component</div>',
            }),
            B: () => ({
                computed: {
                    fooBar() {
                        const prev = this.$super('fooBar');

                        return `${prev}Baz`;
                    },
                    getterSetter: {
                        get() {
                            this.$super('getterSetter.get');

                            return `foo${this._getterSetter}`;
                        },
                        set(value) {
                            this.$super('getterSetter.set', value);

                            this._getterSetter = `${value}Baz!`;
                        },
                    },
                },
                template: '<div>extension-1</div>',
            }),
            C: () => ({
                computed: {
                    fooBar() {
                        const prev = this.$super('fooBar');

                        return `${prev}!`;
                    },
                    getterSetter: {
                        get() {
                            this.$super('getterSetter.get');

                            return this._getterSetter;
                        },
                        set(value) {
                            this.$super('getterSetter.set', value);

                            this._getterSetter = value;
                        },
                    },
                },
                template: '<div>extension-2</div>',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.extend('extension-1', 'test-component', components.B());
                ComponentFactory.extend('extension-2', 'extension-1', components.C());

                const component = shallowMount(await ComponentFactory.build('extension-2'));

                expect(component.vm).toBeTruthy();
                expect(typeof component.vm.fooBar).toBe('string');
                expect(typeof component.vm.$super).toBe('function');
                expect(component.vm.$super('fooBar')).toBe('fooBarBaz');

                component.vm.$super('getterSetter.set', 'Bar');
                expect(component.vm.$super('getterSetter.get')).toBe('fooBarBaz!');
            });
        });
    });

    describe('should build the final component structure overriding a component with computed properties', () => {
        createComponentMatrix({
            A: () => ({
                computed: {
                    fooBar() {
                        return 'fooBar';
                    },
                    getterSetter: {
                        get() {
                            return this._getterSetter;
                        },
                        set(value) {
                            this._getterSetter = value;
                        },
                    },
                },
                template: '<div>test-component</div>',
            }),
            B: () => ({
                computed: {
                    fooBar() {
                        const prev = this.$super('fooBar');

                        return `${prev}Baz`;
                    },
                    getterSetter: {
                        get() {
                            this.$super('getterSetter.get');

                            return `foo${this._getterSetter}`;
                        },
                        set(value) {
                            this.$super('getterSetter.set', value);

                            this._getterSetter = `${value}Baz!`;
                        },
                    },
                },
            }),
            C: () => ({
                computed: {
                    fooBar() {
                        const prev = this.$super('fooBar');

                        return `${prev}!`;
                    },
                    getterSetter: {
                        get() {
                            this.$super('getterSetter.get');

                            return this._getterSetter;
                        },
                        set(value) {
                            this.$super('getterSetter.set', value);

                            this._getterSetter = value;
                        },
                    },
                },
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.override('test-component', components.B());
                ComponentFactory.override('test-component', components.C());

                const component = shallowMount(await ComponentFactory.build('test-component'));

                expect(component.vm).toBeTruthy();
                expect(typeof component.vm.fooBar).toBe('string');
                expect(typeof component.vm.$super).toBe('function');
                expect(component.vm.$super('fooBar')).toBe('fooBarBaz');

                component.vm.$super('getterSetter.set', 'Bar');
                expect(component.vm.$super('getterSetter.get')).toBe('fooBarBaz!');
            });
        });
    });

    describe('should build the final component structure overriding a component only with a template', () => {
        createComponentMatrix({
            A: () => ({
                methods: {
                    fooBar() {
                        return 'fooBar';
                    },
                },
                template: '{% block content %}<div>This is a test template.</div>{% endblock %}',
            }),
            B: () => ({
                methods: {
                    fooBar() {
                        const prev = this.$super('fooBar');

                        return `${prev}Baz`;
                    },
                },
            }),
            C: () => ({
                template: '{% block content %}<div>This is a template override.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.override('test-component', components.B());
                ComponentFactory.override('test-component', components.C());

                const component = shallowMount(await ComponentFactory.build('test-component'));

                expect(component.vm).toBeTruthy();
                expect(typeof component.vm.fooBar).toBe('function');
                expect(typeof component.vm.$super).toBe('function');
                expect(component.vm.$super('fooBar')).toBe('fooBar');
                expect(component.vm.fooBar()).toBe('fooBarBaz');
                expect(component.html()).toContain('<div>This is a template override.</div>');
            });
        });
    });

    describe('should build the $super-call-stack when $super-call is inside an promise chain', () => {
        createComponentMatrix({
            A: () => ({
                methods: {
                    fooBar() {
                        return 'fooBar';
                    },
                },
                template: '{% block content %}<div>This is a test template.</div>{% endblock %}',
            }),
            B: () => ({
                methods: {
                    fooBar() {
                        const p = new Promise((resolve) => {
                            resolve('Baz');
                        });


                        return p.then((value) => {
                            const prev = this.$super('fooBar');

                            return `${prev}${value}`;
                        });
                    },
                },
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('test-component', components.A());
                ComponentFactory.override('test-component', components.B());

                const component = shallowMount(await ComponentFactory.build('test-component'));

                expect(component.vm).toBeTruthy();
                expect(typeof component.vm.fooBar).toBe('function');
                expect(typeof component.vm.$super).toBe('function');
                expect(component.vm.$super('fooBar')).toBe('fooBar');
            });
        });
    });

    describe('should extend an extended component and all three components get build before with usage of parent', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block first %}<div>First.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block first %}{% block second %}<div>Second.</div>{% endblock %}{% endblock %}',
            }),
            C: () => ({
                // eslint-disable-next-line max-len
                template: '{% block second %}<div>{% parent %}{% block third %}<div>Third.</div>{% endblock %}</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('first-component', components.A());
                ComponentFactory.extend('second-component', 'first-component', components.B());
                ComponentFactory.extend('third-component', 'second-component', components.C());
                ComponentFactory.build('first-component');
                ComponentFactory.build('second-component');
                const thirdComponent = await ComponentFactory.build('third-component');
                expect(thirdComponent.template).toBe('<div><div>Second.</div><div>Third.</div></div>');
            });
        });
    });

    describe('should extend an extended component and all four components get build before with multiple usage of parent', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block first %}<div>First.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block first %}{% block second %}<div>Second.</div>{% endblock %}{% endblock %}',
            }),
            C: () => ({
                // eslint-disable-next-line max-len
                template: '{% block second %}<div>{% parent %}{% block third %}<div>Third.</div>{% endblock %}</div>{% endblock %}',
            }),
            D: () => ({
                // eslint-disable-next-line max-len
                template: '{% block second %}<div>{% block fourth %}<div>Fourth.</div>{% parent %}{% endblock %}</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('first-component', components.A());
                ComponentFactory.extend('second-component', 'first-component', components.B());
                ComponentFactory.extend('third-component', 'second-component', components.C());
                ComponentFactory.extend('fourth-component', 'third-component', components.D());

                ComponentFactory.build('first-component');
                ComponentFactory.build('second-component');
                ComponentFactory.build('third-component');
                const fourthComponent = await ComponentFactory.build('fourth-component');
                // eslint-disable-next-line max-len
                expect(fourthComponent.template).toBe('<div><div>Fourth.</div><div><div>Second.</div><div>Third.</div></div></div>');
            });
        });
    });

    describe('should extend an extended component and all five components get build before with multiple usage of parent', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block first %}<div>First.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block first %}{% block second %}<div>Second.</div>{% endblock %}{% endblock %}',
            }),
            C: () => ({
                // eslint-disable-next-line max-len
                template: '{% block second %}<div>{% parent %}{% block third %}<div>Third.</div>{% endblock %}</div>{% endblock %}',
            }),
            D: () => ({
                // eslint-disable-next-line max-len
                template: '{% block second %}<div>{% block fourth %}<div>Fourth.</div>{% endblock %}{% parent %}</div>{% endblock %}',
            }),
            E: () => ({
                // eslint-disable-next-line max-len
                template: '{% block second %}<div>{% block fifth %}<div>Fifth.</div>{% endblock %}{% parent %}</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('first-component', components.B());
                ComponentFactory.extend('second-component', 'first-component', components.B());
                ComponentFactory.extend('third-component', 'second-component', components.C());
                ComponentFactory.extend('fourth-component', 'third-component', components.D());
                ComponentFactory.extend('fifth-component', 'fourth-component', components.E());

                ComponentFactory.build('first-component');
                ComponentFactory.build('second-component');
                ComponentFactory.build('third-component');
                ComponentFactory.build('fourth-component');
                const fifthComponent = await ComponentFactory.build('fifth-component');

                // eslint-disable-next-line max-len
                expect(fifthComponent.template).toBe('<div><div>Fifth.</div><div><div>Fourth.</div><div><div>Second.</div><div>Third.</div></div></div></div>');
            });
        });
    });

    describe('should extend an extended component', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block first %}<div>First.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block first %}{% block second %}<div>Second.</div>{% endblock %}{% endblock %}',
            }),
            C: () => ({
                template: '{% block second %}{% block third %}<div>Third.</div>{% endblock %}{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('first-component', components.A());
                ComponentFactory.extend('second-component', 'first-component', components.B());
                ComponentFactory.extend('third-component', 'second-component', components.C());

                const thirdComponent = await ComponentFactory.build('third-component');
                expect(thirdComponent.template).toBe('<div>Third.</div>');
            });
        });
    });

    describe('should extend an extended component in a mixed order', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block second %}{% block third %}<div>Third.</div>{% endblock %}{% endblock %}',
            }),
            B: () => ({
                template: '{% block first %}{% block second %}<div>Second.</div>{% endblock %}{% endblock %}',
            }),
            C: () => ({
                template: '{% block first %}<div>First.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.extend('third-component', 'second-component', components.A());
                ComponentFactory.extend('second-component', 'first-component', components.B());
                ComponentFactory.register('first-component', components.C());

                const thirdComponent = await ComponentFactory.build('third-component');
                expect(thirdComponent.template).toBe('<div>Third.</div>');
            });
        });
    });

    describe('should extend an extended component and all components get build before', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block first %}<div>First.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block first %}{% block second %}<div>Second.</div>{% endblock %}{% endblock %}',
            }),
            C: () => ({
                template: '{% block second %}{% block third %}<div>Third.</div>{% endblock %}{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('first-component', components.A());
                ComponentFactory.extend('second-component', 'first-component', components.B());
                ComponentFactory.extend('third-component', 'second-component', components.C());

                ComponentFactory.build('first-component');
                ComponentFactory.build('second-component');
                const thirdComponent = await ComponentFactory.build('third-component');
                expect(thirdComponent.template).toBe('<div>Third.</div>');
            });
        });
    });

    describe('should ignore a parent call when the block was not defined in the upper template', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block first %}<div>First.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block first %}{% block second %}{% parent %}{% endblock %}{% endblock %}',
            }),
            C: () => ({
                template: '{% block first %}{% parent %}{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('first-component', components.A());
                ComponentFactory.extend('second-component', 'first-component', components.B());
                ComponentFactory.register('third-component', components.C());

                const secondComponent = await ComponentFactory.build('second-component');
                const thirdComponent = await ComponentFactory.build('third-component');

                // The parent here the block named "first"
                expect(secondComponent.template).toBe('<div>First.</div>');
                expect(thirdComponent.template).toBe('');
            });
        });
    });

    describe('should render a component which extends a component with an override', () => {
        createComponentMatrix({
            A: () => ({
                template: '<div>{% block first %}<div>First.</div>{% endblock %}</div>',
            }),
            B: () => ({
                template: '{% block first %}{% parent %}<div>First overridden.</div>{% endblock %}',
            }),
            C: () => ({
                template: '{% block first %}{% parent %}<div>First extended.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('first-component', components.A());
                ComponentFactory.override('first-component', components.B());
                ComponentFactory.extend('second-component', 'first-component', components.C());

                const firstComponent = await ComponentFactory.build('first-component');
                const secondComponent = await ComponentFactory.build('second-component');

                expect(firstComponent.template).toBe('<div><div>First.</div><div>First overridden.</div></div>');
                expect(secondComponent.template).toBe(
                    '<div><div>First.</div><div>First overridden.</div><div>First extended.</div></div>',
                );
            });
        });
    });

    describe('should render a component which extends a component with multiple overrides in mixed order', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block first %}{% parent %}<div>First overridden-1.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block first %}{% parent %}<div>First extended.</div>{% endblock %}',
            }),
            C: () => ({
                template: '<div>{% block first %}<div>First.</div>{% endblock %}</div>',
            }),
            D: () => ({
                template: '{% block first %}{% parent %}<div>First overridden-2.</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.override('first-component', components.A());
                ComponentFactory.extend('second-component', 'first-component', components.B());
                ComponentFactory.register('first-component', components.C());
                ComponentFactory.override('first-component', components.D());

                const secondComponent = await ComponentFactory.build('second-component');
                const firstComponent = await ComponentFactory.build('first-component');

                expect(firstComponent.template).toBe(
                    '<div><div>First.</div><div>First overridden-1.</div><div>First overridden-2.</div></div>',
                );
                expect(secondComponent.template).toBe(
                    '<div><div>First.</div><div>First overridden-1.</div><div>First overridden-2.</div>' +
                    '<div>First extended.</div></div>',
                );
            });
        });
    });

    describe('with different registration and build order', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block first %}First.{% endblock %}',
            }),
            B: () => ({
                template: '{% block first %}{% parent %} Override-1.{% endblock %}',
            }),
            C: () => ({
                template: '{% block first %}{% parent %} Override-2.{% endblock %}',
            }),
            D: () => ({
                template: '{% block first %}{% parent %} Extension-1.{% endblock %}',
            }),
            E: () => ({
                template: '{% block first %}{% parent %} Extension-2.{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            describe(`${testCase}`, () => {
                const registerFirst = () => ComponentFactory.register('first-component', components.A());
                const registerFirstOverride = () => ComponentFactory.override('first-component', components.B());
                const registerSecondOverride = () => ComponentFactory.override('first-component', components.C());
                const registerFirstExtension = () => ComponentFactory.extend('second-component', 'first-component', components.D());
                const registerSecondExtension = () => ComponentFactory.extend('third-component', 'second-component', components.E());

                it('should render chained extensions with multiple overrides in regular order', async () => {
                    registerFirst();
                    registerFirstOverride();
                    registerSecondOverride();
                    registerFirstExtension();
                    registerSecondExtension();

                    const firstComponent = await ComponentFactory.build('first-component');
                    const secondComponent = await ComponentFactory.build('second-component');
                    const thirdComponent = await ComponentFactory.build('third-component');

                    expect(firstComponent.template).toBe('First. Override-1. Override-2.');
                    expect(secondComponent.template).toBe('First. Override-1. Override-2. Extension-1.');
                    expect(thirdComponent.template).toBe('First. Override-1. Override-2. Extension-1. Extension-2.');
                });

                it('should render chained extensions with multiple overrides in mixed registration order', async () => {
                    registerSecondExtension();
                    registerFirstExtension();
                    registerFirstOverride();
                    registerSecondOverride();
                    registerFirst();

                    const firstComponent = await ComponentFactory.build('first-component');
                    const secondComponent = await ComponentFactory.build('second-component');
                    const thirdComponent = await ComponentFactory.build('third-component');

                    expect(firstComponent.template).toBe('First. Override-1. Override-2.');
                    expect(secondComponent.template).toBe('First. Override-1. Override-2. Extension-1.');
                    expect(thirdComponent.template).toBe('First. Override-1. Override-2. Extension-1. Extension-2.');
                });

                it('should render chained extensions with multiple overrides in mixed build order', async () => {
                    registerFirst();
                    registerFirstOverride();
                    registerSecondOverride();
                    registerFirstExtension();
                    registerSecondExtension();

                    const thirdComponent = await ComponentFactory.build('third-component');
                    const firstComponent = await ComponentFactory.build('first-component');
                    const secondComponent = await ComponentFactory.build('second-component');

                    expect(firstComponent.template).toBe('First. Override-1. Override-2.');
                    expect(secondComponent.template).toBe('First. Override-1. Override-2. Extension-1.');
                    expect(thirdComponent.template).toBe('First. Override-1. Override-2. Extension-1. Extension-2.');
                });

                it(
                    'should render chained extensions with multiple overrides in mixed registration and build order',
                    async () => {
                        registerSecondExtension();
                        registerFirstExtension();
                        registerFirstOverride();
                        registerSecondOverride();
                        registerFirst();

                        const thirdComponent = await ComponentFactory.build('third-component');
                        const secondComponent = await ComponentFactory.build('second-component');
                        const firstComponent = await ComponentFactory.build('first-component');

                        expect(firstComponent.template).toBe('First. Override-1. Override-2.');
                        expect(secondComponent.template).toBe('First. Override-1. Override-2. Extension-1.');
                        expect(thirdComponent.template).toBe('First. Override-1. Override-2. Extension-1. Extension-2.');
                    },
                );
            });
        });
    });

    describe('should render a component which extends a component with an override using a mixed order', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block first %}{% parent %}<div>First overridden.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block first %}{% parent %}<div>First extended.</div>{% endblock %}',
            }),
            C: () => ({
                template: '<div>{% block first %}<div>First.</div>{% endblock %}</div>',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.override('first-component', components.A());
                ComponentFactory.extend('second-component', 'first-component', components.B());
                ComponentFactory.register('first-component', components.C());

                const firstComponent = await ComponentFactory.build('first-component');
                const secondComponent = await ComponentFactory.build('second-component');

                expect(firstComponent.template).toBe('<div><div>First.</div><div>First overridden.</div></div>');
                expect(secondComponent.template).toBe(
                    '<div><div>First.</div><div>First overridden.</div><div>First extended.</div></div>',
                );
            });
        });
    });

    describe('should fix the Social Shopping chain bug', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block first %}{% parent %}<div>First overridden.</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block first %}{% parent %}<div>Second overridden.</div>{% endblock %}',
            }),
            C: () => ({
                template: '{% block first %}{% parent %}<div>First overridden.</div>{% endblock %}',
            }),
            D: () => ({
                template: '{% block first %}foobar{% endblock %}',
            }),
            E: () => ({
                template: '{% block base %}<div>{% block first %}<div>First.</div>{% endblock %}</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                // Social Shopping - sw-sales-channel-detail (override)
                ComponentFactory.override('detail-component', components.A());
                // Storefront - sw-sales-channel-detail (override)
                ComponentFactory.override('detail-component', components.B());
                // Social Shopping - sw-sales-channel-create (override)
                ComponentFactory.override('create-component', components.C());
                // Administration - sw-sales-channel-create (extend)
                ComponentFactory.extend('create-component', 'detail-component', components.D());

                // Administation - sw-sales-channel-detail (register)
                ComponentFactory.register('detail-component', components.E());

                //  <div>foobar<div>First overridden.</div></div>
                const firstComponent = await ComponentFactory.build('detail-component');
                const secondComponent = await ComponentFactory.build('create-component');

                // eslint-disable-next-line max-len
                expect(firstComponent.template).toBe('<div><div>First.</div><div>First overridden.</div><div>Second overridden.</div></div>');
                expect(secondComponent.template).toBe('<div>foobar<div>First overridden.</div></div>');
            });
        });
    });

    describe('should replace all parent placeholders with an empty string when parent was used incorrectly', () => {
        createComponentMatrix({
            A: () => ({
                template: '<div>{% block first %}{% parent %}{% parent %}{% parent %}{% parent %}{% endblock %}</div>',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('first-component', components.A());

                const firstComponent = await ComponentFactory.build('first-component');
                expect(firstComponent.template).toBe('<div></div>');
            });
        });
    });

    describe('correctly builds the super call stack when root component of the inheritance chain does not implement an overridden method', () => {
        createComponentMatrix({
            A: () => ({
                template: '<div>This is a test template.</div>',
            }),
            B: () => ({
                template: '<div>This is a test template.</div>',
                methods: {
                    fooBar() {
                        return 'called';
                    },
                },
            }),
            C: () => ({
                template: '<div>This is a test template.</div>',
                methods: {
                    fooBar() {
                        return this.$super('fooBar');
                    },
                },
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('grandparent-component', components.A());
                ComponentFactory.extend('parent-component', 'grandparent-component', components.B());
                ComponentFactory.extend('child-component', 'parent-component', components.C());

                const childComponent = shallowMount(await ComponentFactory.build('child-component'));

                expect(childComponent.vm.fooBar()).toBe('called');
            });
        });
    });

    describe('correctly builds the super call stack when one component of the inheritance chain does not implement an overridden method', () => {
        createComponentMatrix({
            A: () => ({
                template: '<div>This is a test template.</div>',
                methods: {
                    fooBar() {
                        return 'called';
                    },
                },
            }),
            B: () => ({
                template: '<div>This is a test template.</div>',
            }),
            C: () => ({
                template: '<div>This is a test template.</div>',
                methods: {
                    fooBar() {
                        return this.$super('fooBar');
                    },
                },
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('grandparent-component', components.A());
                ComponentFactory.extend('parent-component', 'grandparent-component', components.B());
                ComponentFactory.extend('child-component', 'parent-component', components.C());

                const childComponent = shallowMount(await ComponentFactory.build('child-component'));

                expect(childComponent.vm.fooBar()).toBe('called');
            });
        });
    });

    describe('correctly builds the super call stack when components in the beginning of the inheritance chain do not implement an overridden method', () => {
        createComponentMatrix({
            A: () => ({
                template: '<div>This is a test template.</div>',
            }),
            B: () => ({
                template: '<div>This is a test template.</div>',
            }),
            C: () => ({
                template: '<div>This is a test template.</div>',
                methods: {
                    fooBar() {
                        return 'called';
                    },
                },
            }),
            D: () => ({
                template: '<div>This is a test template.</div>',
                methods: {
                    fooBar() {
                        return this.$super('fooBar');
                    },
                },
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('great-grandparent-component', components.A());
                ComponentFactory.extend('grandparent-component', 'great-grandparent-component', components.B());
                ComponentFactory.extend('parent-component', 'grandparent-component', components.C());
                ComponentFactory.extend('child-component', 'parent-component', components.D());

                const childComponent = shallowMount(await ComponentFactory.build('child-component'));

                expect(childComponent.vm.fooBar()).toBe('called');
            });
        });
    });

    describe('correctly builds the super call stack when components in the beginning of the inheritance chain do not implement an overridden method when super is called from another super call', () => {
        createComponentMatrix({
            A: () => ({
                template: '<div>This is a test template.</div>',
                created() {
                    this.createdComponent();
                },

                methods: {
                    createdComponent() {
                        this.getData();
                    },

                    getData() {
                        return ['root'];
                    },
                },
            }),
            B: (createdData) => ({
                template: '<div>This is a test template.</div>',
                methods: {
                    getData() {
                        const data = this.$super('getData');
                        data.push('overridden');

                        createdData.push(...data);

                        return data;
                    },
                },
            }),
            C: () => ({
                template: '<div>This is a test template.</div>',
                methods: {
                    createdComponent() {
                        this.$super('createdComponent');
                    },
                },
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                const createdData = [];

                ComponentFactory.register('root-component', components.A());
                ComponentFactory.override('root-component', components.B(createdData));
                ComponentFactory.override('root-component', components.C());

                shallowMount(await ComponentFactory.build('root-component'));

                expect(createdData).toEqual(['root', 'overridden']);
            });
        });
    });

    describe('correctly builds the super call stack when components in the beginning of the inheritance chain do not implement an overridden method when super is called from another super call with four components', () => {
        createComponentMatrix({
            A: () => ({
                template: '<div>This is a test template.</div>',
                created() {
                    this.createdComponent();
                },

                methods: {
                    createdComponent() {
                        this.getData();
                    },

                    getData() {
                        return ['root'];
                    },
                },
            }),
            B: (createdData) => ({
                template: '<div>This is a test template.</div>',
                methods: {
                    getData() {
                        const data = this.$super('getData');
                        data.push('overridden');

                        createdData.push(...data);

                        return data;
                    },
                },
            }),
            C: () => ({
                template: '<div>This is a test template.</div>',
            }),
            D: () => ({
                template: '<div>This is a test template.</div>',
                methods: {
                    createdComponent() {
                        this.$super('createdComponent');
                    },
                },
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                const createdData = [];

                ComponentFactory.register('root-component', components.A());
                ComponentFactory.override('root-component', components.B(createdData));
                ComponentFactory.override('root-component', components.C());
                ComponentFactory.override('root-component', components.D());

                shallowMount(await ComponentFactory.build('root-component'));

                expect(createdData).toEqual(['root', 'overridden']);
            });
        });
    });

    describe('does not modify the override registry for extended components', () => {
        createComponentMatrix({
            A: () => ({
                template: '<div>This is a test template.</div>',
                created() {
                    this.createdComponent();
                },

                methods: {
                    createdComponent() {
                        this.getData();
                    },

                    getData() {
                        return ['root'];
                    },
                },
            }),
            B: () => ({
                template: '<div>This is a test template.</div>',
                methods: {
                    getData() {
                        const data = this.$super('getData');
                        data.push('overridden');

                        return data;
                    },
                },
            }),
            C: () => ({
                template: '<div>This is a test template.</div>',
            }),
            D: () => ({
                template: '<div>This is a test template.</div>',
                methods: {
                    createdComponent() {
                        this.$super('createdComponent');
                    },
                },
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('root-component', components.A());
                ComponentFactory.override('root-component', components.B());
                ComponentFactory.override('root-component', components.C());
                ComponentFactory.override('root-component', components.D());

                shallowMount(await ComponentFactory.build('root-component'));

                const expected = cloneDeep(ComponentFactory.getOverrideRegistry().get('root-component'));

                ComponentFactory.extend('child-component', 'root-component', {
                    template: '<div>This is a test template.</div>',
                });
                ComponentFactory.build('child-component');

                const actual = cloneDeep(ComponentFactory.getOverrideRegistry().get('root-component'));

                expect(expected).toEqual(actual);
            });
        });
    });

    describe('overrides template and use these blocks', () => {
        createComponentMatrix({
            A: () => ({
                template: '<div>{% block test %}This is a test template.{% endblock %}</div>',
            }),
            B: () => ({
                template: '{% block test %}Override{% block new_block %}foo{% endblock %}{% endblock %}',
            }),
            C: () => ({
                template: '{% block new_block %}<div>Test</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('component', components.A());
                ComponentFactory.override('component', components.B());
                ComponentFactory.override('component', components.C());

                const component = await ComponentFactory.build('component');
                expect(component.template).toBe('<div>Override<div>Test</div></div>');
            });
        });
    });

    describe('extends a component which is also an extension without a template', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block sw_settings_user_detail %}<h1>Foo</h1>{% endblock %}',
            }),
            B: () => ({
                template: '{% block sw_settings_user_detail %}<h1>Test</h1>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('root-component', components.A());
                ComponentFactory.extend('child-component', 'root-component', components.B());
                ComponentFactory.extend('grandchild-component', 'child-component');

                const component = await ComponentFactory.build('grandchild-component');
                expect(component.template).toBe('<h1>Test</h1>');
            });
        });
    });

    describe('extends a component with wrapping template', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block text_field %}<input type="text">{% endblock %}',
            }),
            B: () => ({
                template: '{% block password_field %}{% block text_field %}<input type="password">{% endblock %}{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('text-field-component', components.A());
                ComponentFactory.extend('password-field-component', 'text-field-component', components.B());

                const component = await ComponentFactory.build('password-field-component');
                expect(component.template).toBe('<input type="password">');
            });
        });
    });

    describe('override should redeclare blocks if parent is used', () => {
        createComponentMatrix({
            A: () => ({
                // eslint-disable-next-line max-len
                template: '{% block base_component %}<div>{% block content %}This is the base content.{% endblock %}</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block content %}{% parent %} This is the inner override.{% endblock %}',
            }),
            C: () => ({
                template: '{% block base_component %}<div>This is the outer override. {% parent %}</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('base-component', components.A());
                ComponentFactory.override('base-component', components.B());
                ComponentFactory.override('base-component', components.C());

                const component = await ComponentFactory.build('base-component');
                // eslint-disable-next-line max-len
                const expected = '<div>This is the outer override. <div>This is the base content. This is the inner override.</div></div>';

                expect(component.template).toBe(expected);
            });
        });
    });

    describe('allows to override nested blocks', () => {
        createComponentMatrix({
            A: () => ({
                // eslint-disable-next-line max-len
                template: '<div class="root-component">{% block outer_block %}{% block nested_block %}<div>I\'m nested</div>{% endblock %}{% endblock %}</div>',
            }),
            B: () => ({
                template:
                    '{% block outer_block %}Overriding outer block {% parent %} {% endblock %}' +
                    '{% block nested_block %}Overriding inner block {% parent %} {% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('root-component', components.A());
                ComponentFactory.override('root-component', components.B());

                const component = await ComponentFactory.build('root-component');
                // eslint-disable-next-line max-len
                const expected = '<div class="root-component">Overriding outer block Overriding inner block <div>I\'m nested</div>  </div>';

                expect(component.template).toEqual(expected);
            });
        });
    });

    describe('allows to override nested blocks with parent call', () => {
        createComponentMatrix({
            A: () => ({
                // eslint-disable-next-line max-len
                template: '<div class="root-component">{% block outer_block %}Im the outer block {% block nested_block %}<div>I\'m nested</div>{% endblock %}{% endblock %}</div>',
            }),
            B: () => ({
                template:
                    '{% block outer_block %}Overriding outer block {% parent %} {% endblock %}' +
                    '{% block nested_block %}Overriding inner block {% parent %} {% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.register('root-component', components.A());
                ComponentFactory.override('root-component', components.B());

                const component = await ComponentFactory.build('root-component');
                // eslint-disable-next-line max-len
                const expected = '<div class="root-component">Overriding outer block Im the outer block Overriding inner block <div>I\'m nested</div>  </div>';

                expect(component.template).toEqual(expected);
            });
        });
    });

    describe('ignores component overrides or extensions of components that are not registered', () => {
        createComponentMatrix({
            A: () => ({
                template: '{% block text_field %}<div>Not registered</div>{% endblock %}',
            }),
            B: () => ({
                template: '{% block text_field %}<div>Not registered</div>{% endblock %}',
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.override('override-without-register', components.A());
                ComponentFactory.extend('extended-component', 'not-registered', components.B());

                const overriden = () => ComponentFactory.build('override-without-register');
                const extended = () => ComponentFactory.build('extended-component');

                await expect(overriden()).rejects.toThrow('The component registry has not found a component with the name "override-without-register"');
                await expect(extended()).rejects.toThrow('The component registry has not found a component with the name "not-registered"');
            });
        });
    });

    describe('returns a component if it has no template but a render function', () => {
        createComponentMatrix({
            A: () => ({
                render(h) { return h('div', {}, 'i was not registered'); },
            }),
            B: () => ({
                render(h) { return h('div', {}, 'registered component'); },
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                ComponentFactory.override('not-registered-with-render-function', components.A());
                ComponentFactory.register('with-render-function', components.B());

                const overriden = () => ComponentFactory.build('not-registered-with-render-function');
                const registered = () => ComponentFactory.build('with-render-function');

                await expect(overriden()).rejects.toThrow();
                await expect(registered()).resolves.not.toThrow();
            });
        });
    });

    describe('returns a component which has multiple overrides with array based properties', () => {
        createComponentMatrix({
            A: () => ({
                template: '<div class="base-component">{% block overrides %}{% endblock %}</div>',
            }),
            B: () => ({
                template: '{% block overrides %}{% parent %} {{logAnotherService}}{% endblock %}',

                inject: ['someService', 'anotherService'],
                mixins: [{
                    computed: { logSomeService() { return this.someService(); } },
                }, {
                    computed: { logAnotherService() { return this.anotherService(); } },
                }],
            }),
            C: () => ({
                template: '{% block overrides %}{% parent %} {{logSomeService}}{% endblock %}',

                inject: ['someService'],
                mixins: [{
                    computed: { logSomeService() { return this.someService(); } },
                }],
            }),
        }).forEach(({ testCase, components }) => {
            it(`${testCase}`, async () => {
                const componentName = 'baseComponent';

                ComponentFactory.register(componentName, components.A());
                ComponentFactory.override(componentName, components.B());
                ComponentFactory.override(componentName, components.C());

                const buildConfig = await ComponentFactory.build(componentName);

                const wrapper = await shallowMount(buildConfig, {
                    provide: {
                        someService() { return 'foo'; },
                        anotherService() { return 'bar'; },
                    },
                });

                expect(wrapper.html()).toBe('<div class="base-component"> bar foo</div>');

                wrapper.destroy();
            });
        });
    });

    it('should only trigger methods of used components', async () => {
        const componentALoaded = jest.fn();
        const componentBLoaded = jest.fn();

        ComponentFactory.register('component-a', () => {
            componentALoaded();

            return Promise.resolve({
                template: '<h1>Component A</h1>',
            });
        });

        ComponentFactory.register('component-b', () => {
            componentBLoaded();

            return Promise.resolve({
                template: '<h1>Component B</h1>',
            });
        });

        expect(componentALoaded).not.toHaveBeenCalled();
        expect(componentBLoaded).not.toHaveBeenCalled();

        await ComponentFactory.build('component-b');

        expect(componentALoaded).not.toHaveBeenCalled();
        expect(componentBLoaded).toHaveBeenCalled();

        await ComponentFactory.build('component-a');

        expect(componentALoaded).toHaveBeenCalled();
        expect(componentBLoaded).toHaveBeenCalled();
    });

    it('should use the default property by module import when register a component', async () => {
        ComponentFactory.register('component-a', () => Promise.resolve({
            default: {
                template: '<h1>Hello from component A</h1>',
            },
        }));

        ComponentFactory.register('component-b', () => Promise.resolve({
            template: '<h1>Hello from component B</h1>',
        }));

        const componentA = await ComponentFactory.build('component-a');
        const componentB = await ComponentFactory.build('component-b');

        expect(shallowMount(componentA).html()).toBe('<h1>Hello from component A</h1>');
        expect(shallowMount(componentB).html()).toBe('<h1>Hello from component B</h1>');
    });

    it('should use the default property by module import when extend a component', async () => {
        ComponentFactory.register('component-a', () => Promise.resolve({
            default: {
                template: '{% block main %}<h1>Hello from component A{% endblock %}</h1>',
            },
        }));

        ComponentFactory.extend('component-b', 'component-a', () => Promise.resolve({
            default: {
                template: '{% block main %}<h1>Hello from component B{% endblock %}</h1>',
            },
        }));

        ComponentFactory.extend('component-c', 'component-a', () => Promise.resolve({
            template: '{% block main %}<h1>Hello from component C{% endblock %}</h1>',
        }));

        const componentA = await ComponentFactory.build('component-a');
        const componentB = await ComponentFactory.build('component-b');
        const componentC = await ComponentFactory.build('component-c');

        expect(shallowMount(componentA).html()).toBe('<h1>Hello from component A</h1>');
        expect(shallowMount(componentB).html()).toBe('<h1>Hello from component B</h1>');
        expect(shallowMount(componentC).html()).toBe('<h1>Hello from component C</h1>');
    });

    it('should use the default property by module import when override a component', async () => {
        ComponentFactory.register('component-a', () => Promise.resolve({
            default: {
                template: '{% block main %}<h1>Hello from component A{% endblock %}</h1>',
            },
        }));

        ComponentFactory.override('component-a', () => Promise.resolve({
            default: {
                template: '{% block main %}<h1>Hello from the override{% endblock %}</h1>',
            },
        }));

        const componentA = await ComponentFactory.build('component-a');

        expect(shallowMount(componentA).html()).toBe('<h1>Hello from the override</h1>');
    });

    it('should register and execute component helper', async () => {
        const testMethod = jest.fn();

        const success = ComponentFactory.registerComponentHelper('test', () => {
            testMethod();
        });

        expect(success).toBe(true);
        expect(testMethod).not.toHaveBeenCalled();

        const { test } = ComponentFactory.getComponentHelper();
        test();

        expect(testMethod).toHaveBeenCalled();
    });

    it('should not register a component helper when a name is missing', async () => {
        const spy = jest.spyOn(console, 'warn').mockImplementation();
        const success = ComponentFactory.registerComponentHelper('', () => {});

        expect(success).toBe(false);
        expect(spy).toHaveBeenCalledWith(
            '[ComponentFactory/ComponentHelper]',
            'A ComponentHelper always needs a name.',
            expect.any(Function),
        );
    });

    it('should not register a component helper when a helper with the same name exists before', async () => {
        const spy = jest.spyOn(console, 'warn').mockImplementation();
        const success = ComponentFactory.registerComponentHelper('test', () => {});
        expect(success).toBe(true);

        const secondSuccess = ComponentFactory.registerComponentHelper('test', () => {});
        expect(secondSuccess).toBe(false);
        expect(spy).toHaveBeenCalledWith(
            '[ComponentFactory/ComponentHelper]',
            'A ComponentHelper with the name test already exists.',
            expect.any(Function),
        );
    });

    it('should return the same input config as output config', async () => {
        const inputConfig = {
            data() {
                return {
                    test: 'data',
                };
            },
        };

        const outputConfig = ComponentFactory.wrapComponentConfig(inputConfig);
        expect(inputConfig).toBe(outputConfig);
    });
});
