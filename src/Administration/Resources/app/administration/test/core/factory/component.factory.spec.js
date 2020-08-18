import { shallowMount } from '@vue/test-utils';
import ComponentFactory from 'src/core/factory/component.factory';
import TemplateFactory from 'src/core/factory/template.factory';
import { cloneDeep } from 'src/core/service/utils/object.utils';

// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

beforeEach(() => {
    ComponentFactory.getComponentRegistry().clear();
    ComponentFactory.getOverrideRegistry().clear();
    TemplateFactory.getTemplateRegistry().clear();
    TemplateFactory.getNormalizedTemplateRegistry().clear();
    TemplateFactory.disableTwigCache();
    ComponentFactory.markComponentTemplatesAsNotResolved();
});

describe('core/factory/component.factory.js', () => {
    it(
        'should register a component and it should be registered in the component registry',
        () => {
            const component = ComponentFactory.register('test-component', {
                template: '<div>This is a test template.</div>'
            });

            const registry = ComponentFactory.getComponentRegistry();

            expect(typeof component).toBe('object');
            expect(registry.has('test-component')).toBe(true);
            expect(typeof registry.get('test-component')).toBe('object');
        }
    );

    it(
        'should not be possible to register a component with the same name twice',
        () => {
            const compDefinition = {
                template: '<div>This is a test template.</div>'
            };

            ComponentFactory.register('test-component', compDefinition);
            const component = ComponentFactory.register('test-component', compDefinition);

            expect(component).toBe(false);
        }
    );

    it('should not be possible to register a component without a name', () => {
        const component = ComponentFactory.register('', {
            template: '<div>This is a test template.</div>'
        });

        expect(component).toBe(false);
    });

    it(
        'should not be possible to register a component without a template',
        () => {
            const component = ComponentFactory.register('test-component', {});

            expect(component).toBe(false);
        }
    );

    it(
        'should not have a template property after registering a component',
        () => {
            const component = ComponentFactory.register('test-component', {
                template: '<div>This is a test template.</div>'
            });

            expect(component.template).toBe(undefined);
        }
    );

    it('should extend a given component & should register a new component (without template)', () => {
        ComponentFactory.register('test-component', {
            created() {},
            template: '<div>This is a test template.</div>'
        });

        const extension = ComponentFactory.extend('test-component-extension', 'test-component', {
            updated() {}
        });

        const registry = ComponentFactory.getComponentRegistry();

        expect(typeof extension.updated).toBe('function');
        expect(typeof extension.extends).toBe('string');
        expect(extension.extends).toBe('test-component');
        expect(registry.has('test-component-extension')).toBe(true);
        expect(typeof registry.get('test-component-extension')).toBe('object');
    });

    it('should extend a given component & should register a new component (with template)', () => {
        ComponentFactory.register('test-component', {
            created() {},
            template: '<div>This is a test template.</div>'
        });

        const extension = ComponentFactory.extend('test-component-extension', 'test-component', {
            updated() {},
            template: '<div>This is an extension.</div>'
        });

        const registry = ComponentFactory.getComponentRegistry();

        expect(typeof extension.updated).toBe('function');
        expect(typeof extension.extends).toBe('string');
        expect(extension.extends).toBe('test-component');
        expect(registry.has('test-component-extension')).toBe(true);
        expect(typeof registry.get('test-component-extension')).toBe('object');
        expect(extension.template).toBe(undefined);
    });

    it(
        'should register an override of an existing component in the override registry (without index)',
        () => {
            ComponentFactory.register('test-component', {
                created() {},
                methods: {
                    testMethod() {
                        return 'This is a test method.';
                    }
                },
                template: '<div>This is a test template.</div>'
            });

            const override = ComponentFactory.override('test-component', {
                methods: {
                    testMethod() {
                        return 'This is an override.';
                    }
                },
                template: '<div>This is an override.</div>'
            });

            const registry = ComponentFactory.getComponentRegistry();
            const overrideRegistry = ComponentFactory.getOverrideRegistry();

            expect(typeof override.methods.testMethod).toBe('function');
            expect(override.template).toBe(undefined);
            expect(registry.has('test-component')).toBe(true);
            expect(typeof registry.get('test-component')).toBe('object');
            expect(overrideRegistry.has('test-component')).toBe(true);
            expect(overrideRegistry.get('test-component')).toBeInstanceOf(Array);
            expect(overrideRegistry.get('test-component').length).toBe(1);
            expect(overrideRegistry.get('test-component')[0]).toBeInstanceOf(Object);
        }
    );

    it(
        'should register two overrides of an existing component in the override registry (with index)',
        () => {
            ComponentFactory.register('test-component', {
                created() {},
                methods: {
                    testMethod() {
                        return 'This is a test method.';
                    }
                },
                template: '<div>This is a test template.</div>'
            });

            const overrideOne = ComponentFactory.override('test-component', {
                methods: {
                    testMethod() {
                        return 'This is the first override.';
                    }
                }
            });

            const overrideTwo = ComponentFactory.override('test-component', {
                methods: {
                    testMethod() {
                        return 'This is the second override.';
                    }
                }
            }, 0);

            const registry = ComponentFactory.getComponentRegistry();
            const overrideRegistry = ComponentFactory.getOverrideRegistry();

            expect(typeof overrideOne.methods.testMethod).toBe('function');
            expect(typeof overrideTwo.methods.testMethod).toBe('function');
            expect(overrideOne.template).toBe(undefined);
            expect(overrideTwo.template).toBe(undefined);
            expect(registry.has('test-component')).toBe(true);
            expect(registry.get('test-component')).toBeInstanceOf(Object);
            expect(overrideRegistry.has('test-component')).toBe(true);
            expect(overrideRegistry.get('test-component')).toBeInstanceOf(Array);
            expect(overrideRegistry.get('test-component').length).toBe(2);
            expect(overrideRegistry.get('test-component')[0]).toBeInstanceOf(Object);
            expect(overrideRegistry.get('test-component')[1]).toBeInstanceOf(Object);
            expect(typeof overrideRegistry.get('test-component')[0].methods.testMethod).toBe('function');
            expect(typeof overrideRegistry.get('test-component')[1].methods.testMethod).toBe('function');
            expect(overrideRegistry.get('test-component')[0].methods.testMethod()).toBe('This is the second override.');
            expect(overrideRegistry.get('test-component')[1].methods.testMethod()).toBe('This is the first override.');
        }
    );

    it(
        'should provide the rendered template of a component including overrides',
        () => {
            ComponentFactory.register('test-component', {
                template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
            });

            ComponentFactory.override('test-component', {
                template: '{% block content %}<div>This is a template override.</div>{% endblock %}'
            });

            const overriddenTemplate = ComponentFactory.getComponentTemplate('test-component');
            expect(overriddenTemplate).toBe('<div>This is a template override.</div>');
        }
    );

    it('should extend a block within a component', () => {
        ComponentFactory.register('test-component', {
            template: '{% block content %}<div>This is the {% block name %}base{% endblock %} component</div>{% endblock %}'
        });

        ComponentFactory.extend('test-component-extension', 'test-component', {
            template: '{% block name %}extended{% endblock %}'
        });

        const renderedTemplate = ComponentFactory.getComponentTemplate('test-component');
        const extendedTemplate = ComponentFactory.getComponentTemplate('test-component-extension');

        expect(renderedTemplate).toBe('<div>This is the base component</div>');
        expect(extendedTemplate).toBe('<div>This is the extended component</div>');
    });

    it('should be able to extend a component before itself was registered', () => {
        ComponentFactory.extend('test-component-extension', 'test-component', {
            template: '{% block base %}<div>This is a template override.</div>{% endblock %}'
        });

        ComponentFactory.register('test-component', {
            template: '{% block base %}<div>This is a test template.</div>{% endblock %}'
        });

        const renderedTemplate = ComponentFactory.getComponentTemplate('test-component');
        const extendedTemplate = ComponentFactory.getComponentTemplate('test-component-extension');

        expect(renderedTemplate).toBe('<div>This is a test template.</div>');
        expect(extendedTemplate).toBe('<div>This is a template override.</div>');
    });

    it('should be able to extend a component with blocks before itself was registered', () => {
        ComponentFactory.extend('test-component-extension', 'test-component', {
            template: '{% block content %}<div>This is a template override.</div>{% endblock %}'
        });

        ComponentFactory.register('test-component', {
            template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
        });

        const renderedTemplate = ComponentFactory.getComponentTemplate('test-component');
        const extendedTemplate = ComponentFactory.getComponentTemplate('test-component-extension');

        expect(renderedTemplate).toBe('<div>This is a test template.</div>');
        expect(extendedTemplate).toBe('<div>This is a template override.</div>');
    });

    it('should be able to override a component before itself was registered', () => {
        ComponentFactory.override('test-component', {
            template: '{% block content %}<div>This is a template override.</div>{% endblock %}'
        });

        ComponentFactory.register('test-component', {
            template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
        });

        const template = ComponentFactory.getComponentTemplate('test-component');
        expect(template).toBe('<div>This is a template override.</div>');
    });

    it('should ignore overrides if block does not exists', () => {
        ComponentFactory.override('test-component', {
            template: '{% block name %}<div>This is a template override.</div>{% endblock %}'
        });

        ComponentFactory.register('test-component', {
            template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
        });

        const overriddenTemplate = ComponentFactory.getComponentTemplate('test-component');

        expect(overriddenTemplate).toBe('<div>This is a test template.</div>');
    });

    it('should ignore overrides if override has no blocks', () => {
        ComponentFactory.override('test-component', {
            template: '{% block name %}<div>This is a template override.</div>{% endblock %}'
        });

        ComponentFactory.register('test-component', {
            template: '<div>This is a test template.</div>'
        });

        const overriddenTemplate = ComponentFactory.getComponentTemplate('test-component');

        expect(overriddenTemplate).toBe('<div>This is a test template.</div>');
    });

    it('should build the final component structure with extension', () => {
        ComponentFactory.register('test-component', {
            created() {},
            methods: {
                testMethod() {
                    return 'This is a test method.';
                }
            },
            template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
        });

        ComponentFactory.extend('test-component-extension', 'test-component', {
            methods: {
                testMethod() {
                    return 'This is an extension.';
                }
            },
            template: '{% block content %}<div>This is an extended template.</div>{% endblock %}'
        });

        const component = ComponentFactory.build('test-component');
        const extension = ComponentFactory.build('test-component-extension');

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
        expect(extension.extends.template).toBe(undefined);
        expect(extension.extends.methods).toBeInstanceOf(Object);
        expect(typeof extension.extends.methods.testMethod).toBe('function');
        expect(extension.extends.methods.testMethod()).toBe('This is a test method.');
    });

    it('should build multiple extended component with parent template', () => {
        ComponentFactory.register('test-component', {
            template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
        });

        ComponentFactory.extend('test-component-child', 'test-component', {
            template: '{% block content %}<div>{% parent %}I am a child.</div>{% endblock %}'
        });

        ComponentFactory.extend('test-component-grandchild', 'test-component-child', {
            template: '{% block content %}<div>{% parent %}I am a grandchild.</div>{% endblock %}'
        });

        const base = ComponentFactory.build('test-component');
        const child = ComponentFactory.build('test-component-child');
        const grandchild = ComponentFactory.build('test-component-grandchild');

        expect(base.template).toBe('<div>This is a test template.</div>');
        expect(child.template).toBe('<div><div>This is a test template.</div>I am a child.</div>');

        // eslint-disable-next-line max-len
        expect(grandchild.template).toBe('<div><div><div>This is a test template.</div>I am a child.</div>I am a grandchild.</div>');
    });

    it('should build the final component structure with an override', () => {
        ComponentFactory.register('test-component', {
            created() {},
            methods: {
                testMethod() {
                    return 'This is a test method.';
                }
            },
            template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
        });

        ComponentFactory.override('test-component', {
            methods: {
                testMethod() {
                    return 'This is an override.';
                }
            },
            template: '{% block content %}<div>This is an override of a template.</div>{% endblock %}'
        });

        const component = ComponentFactory.build('test-component');

        expect(component).toBeInstanceOf(Object);
        expect(component.methods).toBeInstanceOf(Object);
        expect(typeof component.methods.testMethod).toBe('function');
        expect(component.methods.testMethod()).toBe('This is an override.');
        expect(component.template).toBe('<div>This is an override of a template.</div>');

        expect(component.extends).toBeInstanceOf(Object);
        expect(component.extends.template).toBe(undefined);
        expect(component.extends.methods).toBeInstanceOf(Object);
        expect(typeof component.extends.methods.testMethod).toBe('function');
        expect(component.extends.methods.testMethod()).toBe('This is a test method.');
    });

    it('should build the final component structure with an override with parent', () => {
        ComponentFactory.register('test-component', {
            created() {},
            methods: {
                testMethod() {
                    return 'This is a test method.';
                }
            },
            template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
        });

        ComponentFactory.override('test-component', {
            methods: {
                testMethod() {
                    return 'This is an override.';
                }
            },
            template: '{% block content %}<div>{% parent %}This is an override of a template.</div>{% endblock %}'
        });

        const component = ComponentFactory.build('test-component');

        expect(component).toBeInstanceOf(Object);
        expect(component.methods).toBeInstanceOf(Object);
        expect(typeof component.methods.testMethod).toBe('function');
        expect(component.methods.testMethod()).toBe('This is an override.');
        expect(component.template).toBe('<div><div>This is a test template.</div>This is an override of a template.</div>');

        expect(component.extends).toBeInstanceOf(Object);
        expect(component.extends.template).toBe(undefined);
        expect(component.extends.methods).toBeInstanceOf(Object);
        expect(typeof component.extends.methods.testMethod).toBe('function');
        expect(component.extends.methods.testMethod()).toBe('This is a test method.');
    });

    it('should build the final component structure with multiple overrides', () => {
        ComponentFactory.register('test-component', {
            created() {},
            methods: {
                singleOverride() {
                    return 'This method should be overridden once.';
                },
                doubleOverride() {
                    return 'This method should be overridden twice.';
                }
            },
            template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
        });

        ComponentFactory.override('test-component', {
            methods: {
                singleOverride() {
                    return 'This is the first override.';
                },
                doubleOverride() {
                    return 'This is the first override.';
                }
            },
            template: '{% block content %}<div>{% parent %}This is an override of a template.</div>{% endblock %}'
        });

        const componentAfterFirstOverride = ComponentFactory.build('test-component');
        ComponentFactory.markComponentTemplatesAsNotResolved();

        ComponentFactory.override('test-component', {
            methods: {
                doubleOverride() {
                    return 'This is the second override.';
                }
            },
            // eslint-disable-next-line max-len
            template: '{% block content %}<div>{% parent %}This is an override of an overridden template.</div>{% endblock %}'
        });

        const componentAfterSecondOverride = ComponentFactory.build('test-component');

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
        expect(componentAfterSecondOverride.extends.template).toBe(undefined);
        expect(componentAfterSecondOverride.extends.methods).toBeInstanceOf(Object);
    });

    it('should build the final component structure with an extend and super-call', () => {
        ComponentFactory.register('test-component', {
            methods: {
                testMethod() {
                    return 'This is a test method.';
                }
            },
            template: '<div>test-component</div>'
        });

        ComponentFactory.extend('extended-component', 'test-component', {
            methods: {
                testMethod() {
                    const prev = this.$super('testMethod');

                    return `This is an override. ${prev}`;
                }
            },
            template: '<div>extended-component</div>'
        });

        const component = shallowMount(ComponentFactory.build('extended-component'));

        expect(component.isVueInstance()).toBe(true);
        expect(component.vm.testMethod()).toBe('This is an override. This is a test method.');
    });

    it('should build the final component structure with an override and super-call', () => {
        ComponentFactory.register('test-component', {
            methods: {
                testMethod() {
                    return 'This is a test method.';
                }
            },
            template: '<div>test-component</div>'
        });

        ComponentFactory.override('test-component', {
            methods: {
                testMethod() {
                    const prev = this.$super('testMethod');

                    return `This is an override. ${prev}`;
                }
            }
        });

        const component = shallowMount(ComponentFactory.build('test-component'));

        expect(component.isVueInstance()).toBe(true);
        expect(component.vm.testMethod()).toBe('This is an override. This is a test method.');
    });

    it('should build the final component structure with an overriden override and super-call', () => {
        ComponentFactory.register('test-component', {
            methods: {
                testMethod() {
                    return 'This is a test method.';
                }
            },
            template: '<div>test-component</div>'
        });

        ComponentFactory.override('test-component', {
            methods: {
                testMethod() {
                    const prev = this.$super('testMethod');

                    return `This is an override. ${prev}`;
                }
            }
        });

        ComponentFactory.override('test-component', {
            methods: {
                testMethod() {
                    const prev = this.$super('testMethod');

                    return `This is an overridden override. ${prev}`;
                }
            }
        });

        const component = shallowMount(ComponentFactory.build('test-component'));

        expect(component.isVueInstance()).toBe(true);
        expect(component.vm.testMethod())
            .toBe('This is an overridden override. This is an override. This is a test method.');
    });

    it('should build the final component structure with multiple inheritance and super-call', () => {
        ComponentFactory.register('test-component', {
            methods: {
                testMethod() {
                    return 'This is a test method.';
                }
            },
            template: '<div>test-component</div>'
        });

        ComponentFactory.extend('extension-1', 'test-component', {
            methods: {
                testMethod() {
                    const prev = this.$super('testMethod');

                    return `This is an extension. ${prev}`;
                }
            },
            template: '<div>extension-1</div>'
        });

        ComponentFactory.extend('extension-2', 'extension-1', {
            methods: {
                testMethod() {
                    const prev = this.$super('testMethod');

                    return `This is an extended extension. ${prev}`;
                }
            },
            template: '<div>extension-2</div>'
        });

        const component = shallowMount(ComponentFactory.build('extension-2'));

        expect(component.isVueInstance()).toBe(true);
        expect(component.vm.testMethod())
            .toBe('This is an extended extension. This is an extension. This is a test method.');
    });

    it('should build the final component structure extending a component with computed properties', () => {
        ComponentFactory.register('test-component', {
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
                    }
                }
            },
            template: '<div>test-component</div>'
        });

        ComponentFactory.extend('extension-1', 'test-component', {
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
                    }
                }
            },
            template: '<div>extension-1</div>'
        });

        ComponentFactory.extend('extension-2', 'extension-1', {
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
                    }
                }
            },
            template: '<div>extension-2</div>'
        });

        const component = shallowMount(ComponentFactory.build('extension-2'));

        expect(component.isVueInstance()).toBe(true);
        expect(typeof component.vm.fooBar).toBe('string');
        expect(typeof component.vm.$super).toBe('function');
        expect(component.vm.$super('fooBar')).toBe('fooBarBaz');

        component.vm.$super('getterSetter.set', 'Bar');
        expect(component.vm.$super('getterSetter.get')).toBe('fooBarBaz!');
    });

    it('should build the final component structure overriding a component with computed properties', () => {
        ComponentFactory.register('test-component', {
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
                    }
                }
            },
            template: '<div>test-component</div>'
        });

        ComponentFactory.override('test-component', {
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
                    }
                }
            }
        });

        ComponentFactory.override('test-component', {
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
                    }
                }
            }
        });

        const component = shallowMount(ComponentFactory.build('test-component'));

        expect(component.isVueInstance()).toBe(true);
        expect(typeof component.vm.fooBar).toBe('string');
        expect(typeof component.vm.$super).toBe('function');
        expect(component.vm.$super('fooBar')).toBe('fooBarBaz');

        component.vm.$super('getterSetter.set', 'Bar');
        expect(component.vm.$super('getterSetter.get')).toBe('fooBarBaz!');
    });

    it('should build the final component structure overriding a component only with a template', () => {
        ComponentFactory.register('test-component', {
            methods: {
                fooBar() {
                    return 'fooBar';
                }
            },
            template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
        });

        ComponentFactory.override('test-component', {
            methods: {
                fooBar() {
                    const prev = this.$super('fooBar');

                    return `${prev}Baz`;
                }
            }
        });

        ComponentFactory.override('test-component', {
            template: '{% block content %}<div>This is a template override.</div>{% endblock %}'
        });

        const component = shallowMount(ComponentFactory.build('test-component'));

        expect(component.isVueInstance()).toBe(true);
        expect(typeof component.vm.fooBar).toBe('function');
        expect(typeof component.vm.$super).toBe('function');
        expect(component.vm.$super('fooBar')).toBe('fooBar');
        expect(component.vm.fooBar()).toBe('fooBarBaz');
        expect(component.html()).toContain('<div>This is a template override.</div>');
    });

    it('should build the $super-call-stack when $super-call is inside an promise chain', () => {
        ComponentFactory.register('test-component', {
            methods: {
                fooBar() {
                    return 'fooBar';
                }
            },
            template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
        });

        ComponentFactory.override('test-component', {
            methods: {
                fooBar() {
                    const p = new Promise((resolve) => {
                        resolve('Baz');
                    });


                    return p.then((value) => {
                        const prev = this.$super('fooBar');

                        return `${prev}${value}`;
                    });
                }
            }
        });

        const component = shallowMount(ComponentFactory.build('test-component'));

        expect(component.isVueInstance()).toBe(true);
        expect(typeof component.vm.fooBar).toBe('function');
        expect(typeof component.vm.$super).toBe('function');
        expect(component.vm.$super('fooBar')).toBe('fooBar');
    });

    it('should extend an extended component and all three components get build before with usage of parent', () => {
        ComponentFactory.register('first-component', {
            template: '{% block first %}<div>First.</div>{% endblock %}'
        });
        ComponentFactory.extend('second-component', 'first-component', {
            template: '{% block first %}{% block second %}<div>Second.</div>{% endblock %}{% endblock %}'
        });
        ComponentFactory.extend('third-component', 'second-component', {
            // eslint-disable-next-line max-len
            template: '{% block second %}<div>{% parent %}{% block third %}<div>Third.</div>{% endblock %}</div>{% endblock %}'
        });
        ComponentFactory.build('first-component');
        ComponentFactory.build('second-component');
        const thirdComponent = ComponentFactory.build('third-component');
        expect(thirdComponent.template).toBe('<div><div>Second.</div><div>Third.</div></div>');
    });

    it('should extend an extended component and all four components get build before with multiple usage of parent', () => {
        ComponentFactory.register('first-component', {
            template: '{% block first %}<div>First.</div>{% endblock %}'
        });
        ComponentFactory.extend('second-component', 'first-component', {
            template: '{% block first %}{% block second %}<div>Second.</div>{% endblock %}{% endblock %}'
        });
        ComponentFactory.extend('third-component', 'second-component', {
            // eslint-disable-next-line max-len
            template: '{% block second %}<div>{% parent %}{% block third %}<div>Third.</div>{% endblock %}</div>{% endblock %}'
        });
        ComponentFactory.extend('fourth-component', 'third-component', {
            // eslint-disable-next-line max-len
            template: '{% block second %}<div>{% block fourth %}<div>Fourth.</div>{% parent %}{% endblock %}</div>{% endblock %}'
        });

        ComponentFactory.build('first-component');
        ComponentFactory.build('second-component');
        ComponentFactory.build('third-component');
        const fourthComponent = ComponentFactory.build('fourth-component');
        expect(fourthComponent.template).toBe('<div><div>Fourth.</div><div><div>Second.</div><div>Third.</div></div></div>');
    });

    it('should extend an extended component and all five components get build before with multiple usage of parent', () => {
        ComponentFactory.register('first-component', {
            template: '{% block first %}<div>First.</div>{% endblock %}'
        });
        ComponentFactory.extend('second-component', 'first-component', {
            template: '{% block first %}{% block second %}<div>Second.</div>{% endblock %}{% endblock %}'
        });
        ComponentFactory.extend('third-component', 'second-component', {
            // eslint-disable-next-line max-len
            template: '{% block second %}<div>{% parent %}{% block third %}<div>Third.</div>{% endblock %}</div>{% endblock %}'
        });
        ComponentFactory.extend('fourth-component', 'third-component', {
            // eslint-disable-next-line max-len
            template: '{% block second %}<div>{% block fourth %}<div>Fourth.</div>{% endblock %}{% parent %}</div>{% endblock %}'
        });
        ComponentFactory.extend('fifth-component', 'fourth-component', {
            // eslint-disable-next-line max-len
            template: '{% block second %}<div>{% block fifth %}<div>Fifth.</div>{% endblock %}{% parent %}</div>{% endblock %}'
        });

        ComponentFactory.build('first-component');
        ComponentFactory.build('second-component');
        ComponentFactory.build('third-component');
        ComponentFactory.build('fourth-component');
        const fifthComponent = ComponentFactory.build('fifth-component');

        // eslint-disable-next-line max-len
        expect(fifthComponent.template).toBe('<div><div>Fifth.</div><div><div>Fourth.</div><div><div>Second.</div><div>Third.</div></div></div></div>');
    });

    it('should extend an extended component', () => {
        ComponentFactory.register('first-component', {
            template: '{% block first %}<div>First.</div>{% endblock %}'
        });

        ComponentFactory.extend('second-component', 'first-component', {
            template: '{% block first %}{% block second %}<div>Second.</div>{% endblock %}{% endblock %}'
        });

        ComponentFactory.extend('third-component', 'second-component', {
            template: '{% block second %}{% block third %}<div>Third.</div>{% endblock %}{% endblock %}'
        });

        const thirdComponent = ComponentFactory.build('third-component');
        expect(thirdComponent.template).toBe('<div>Third.</div>');
    });

    it('should extend an extended component and all components get build before', () => {
        ComponentFactory.register('first-component', {
            template: '{% block first %}<div>First.</div>{% endblock %}'
        });

        ComponentFactory.extend('second-component', 'first-component', {
            template: '{% block first %}{% block second %}<div>Second.</div>{% endblock %}{% endblock %}'
        });

        ComponentFactory.extend('third-component', 'second-component', {
            template: '{% block second %}{% block third %}<div>Third.</div>{% endblock %}{% endblock %}'
        });

        ComponentFactory.build('first-component');
        ComponentFactory.build('second-component');
        const thirdComponent = ComponentFactory.build('third-component');
        expect(thirdComponent.template).toBe('<div>Third.</div>');
    });

    it('should ignore a parent call when the block was not defined in the upper template', () => {
        ComponentFactory.register('first-component', {
            template: '{% block first %}<div>First.</div>{% endblock %}'
        });

        ComponentFactory.extend('second-component', 'first-component', {
            template: '{% block first %}{% block second %}{% parent %}{% endblock %}{% endblock %}'
        });

        ComponentFactory.register('third-component', {
            template: '{% block first %}{% parent %}{% endblock %}'
        });

        const secondComponent = ComponentFactory.build('second-component');
        const thirdComponent = ComponentFactory.build('third-component');

        // The parent here the block named "first"
        expect(secondComponent.template).toBe('<div>First.</div>');
        expect(thirdComponent.template).toBe('');
    });

    it('should render a component which extends a component with an override', () => {
        ComponentFactory.register('first-component', {
            template: '<div>{% block first %}<div>First.</div>{% endblock %}</div>'
        });

        ComponentFactory.override('first-component', {
            template: '{% block first %}{% parent %}<div>First overridden.</div>{% endblock %}'
        });

        ComponentFactory.extend('second-component', 'first-component', {
            template: '{% block first %}{% parent %}<div>Second.</div>{% endblock %}'
        });

        const firstComponent = ComponentFactory.build('first-component');
        const secondComponent = ComponentFactory.build('second-component');

        expect(firstComponent.template).toBe('<div><div>First.</div><div>First overridden.</div></div>');
        expect(secondComponent.template).toBe('<div><div>First.</div><div>Second.</div></div>');
    });

    it('should render a component which extends a component with an override using a mixed order', () => {
        ComponentFactory.override('first-component', {
            template: '{% block first %}{% parent %}<div>First overridden.</div>{% endblock %}'
        });

        ComponentFactory.extend('second-component', 'first-component', {
            template: '{% block first %}{% parent %}<div>Second.</div>{% endblock %}'
        });

        ComponentFactory.register('first-component', {
            template: '<div>{% block first %}<div>First.</div>{% endblock %}</div>'
        });

        const firstComponent = ComponentFactory.build('first-component');
        const secondComponent = ComponentFactory.build('second-component');

        expect(firstComponent.template).toBe('<div><div>First.</div><div>First overridden.</div></div>');
        expect(secondComponent.template).toBe('<div><div>First.</div><div>Second.</div></div>');
    });

    it('should fix the Social Shopping chain bug', () => {
        // Social Shopping - sw-sales-channel-detail (override)
        ComponentFactory.override('detail-component', {
            template: '{% block first %}{% parent %}<div>First overridden.</div>{% endblock %}'
        });

        // Storefront - sw-sales-channel-detail (override)
        ComponentFactory.override('detail-component', {
            template: '{% block first %}{% parent %}<div>Second overridden.</div>{% endblock %}'
        });

        // Social Shopping - sw-sales-channel-create (override)
        ComponentFactory.override('create-component', {
            template: '{% block first %}{% parent %}<div>First overridden.</div>{% endblock %}'
        });

        // Administration - sw-sales-channel-create (extend)
        ComponentFactory.extend('create-component', 'detail-component', {
            template: '{% block first %}foobar{% endblock %}'
        });

        // Administation - sw-sales-channel-detail (register)
        ComponentFactory.register('detail-component', {
            template: '{% block base %}<div>{% block first %}<div>First.</div>{% endblock %}</div>{% endblock %}'
        });

        //  <div>foobar<div>First overridden.</div></div>
        const firstComponent = ComponentFactory.build('detail-component');
        const secondComponent = ComponentFactory.build('create-component');

        // eslint-disable-next-line max-len
        expect(firstComponent.template).toBe('<div><div>First.</div><div>First overridden.</div><div>Second overridden.</div></div>');
        expect(secondComponent.template).toBe('<div>foobar<div>First overridden.</div></div>');
    });

    it('should replace all parent placeholders with an empty string when parent was used incorrectly', () => {
        ComponentFactory.register('first-component', {
            template: '<div>{% block first %}{% parent %}{% parent %}{% parent %}{% parent %}{% endblock %}</div>'
        });

        const firstComponent = ComponentFactory.build('first-component');
        expect(firstComponent.template).toBe('<div></div>');
    });

    it(
        // eslint-disable-next-line max-len
        'correctly builds the super call stack when root component of the inheritance chain does not implement an overridden method',
        () => {
            ComponentFactory.register('grandparent-component', {
                template: '<div>This is a test template.</div>'
            });
            ComponentFactory.extend('parent-component', 'grandparent-component', {
                template: '<div>This is a test template.</div>',
                methods: {
                    fooBar() {
                        return 'called';
                    }
                }
            });
            ComponentFactory.extend('child-component', 'parent-component', {
                template: '<div>This is a test template.</div>',
                methods: {
                    fooBar() {
                        return this.$super('fooBar');
                    }
                }
            });

            const childComponent = shallowMount(ComponentFactory.build('child-component'));

            expect(childComponent.vm.fooBar()).toBe('called');
        }
    );

    it(
        // eslint-disable-next-line max-len
        'correctly builds the super call stack when one component of the inheritance chain does not implement an overridden method',
        () => {
            ComponentFactory.register('grandparent-component', {
                template: '<div>This is a test template.</div>',
                methods: {
                    fooBar() {
                        return 'called';
                    }
                }
            });
            ComponentFactory.extend('parent-component', 'grandparent-component', {
                template: '<div>This is a test template.</div>'
            });
            ComponentFactory.extend('child-component', 'parent-component', {
                template: '<div>This is a test template.</div>',
                methods: {
                    fooBar() {
                        return this.$super('fooBar');
                    }
                }
            });

            const childComponent = shallowMount(ComponentFactory.build('child-component'));

            expect(childComponent.vm.fooBar()).toBe('called');
        }
    );

    it(
        // eslint-disable-next-line max-len
        'correctly builds the super call stack when components in the beginning of the inheritance chain do not implement an overridden method',
        () => {
            ComponentFactory.register('great-grandparent-component', {
                template: '<div>This is a test template.</div>'
            });
            ComponentFactory.extend('grandparent-component', 'great-grandparent-component', {
                template: '<div>This is a test template.</div>'
            });
            ComponentFactory.extend('parent-component', 'grandparent-component', {
                template: '<div>This is a test template.</div>',
                methods: {
                    fooBar() {
                        return 'called';
                    }
                }
            });
            ComponentFactory.extend('child-component', 'parent-component', {
                template: '<div>This is a test template.</div>',
                methods: {
                    fooBar() {
                        return this.$super('fooBar');
                    }
                }
            });

            const childComponent = shallowMount(ComponentFactory.build('child-component'));

            expect(childComponent.vm.fooBar()).toBe('called');
        },
    );

    it(
        // eslint-disable-next-line max-len
        'correctly builds the super call stack when components in the beginning of the inheritance chain do not implement an overridden method when super is called from another super call',
        () => {
            let createdData = [];
            ComponentFactory.register('root-component', {
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
                    }
                }
            });
            ComponentFactory.override('root-component', {
                template: '<div>This is a test template.</div>',
                methods: {
                    getData() {
                        const data = this.$super('getData');
                        data.push('overridden');

                        createdData = data;

                        return data;
                    }
                }
            });
            ComponentFactory.override('root-component', {
                template: '<div>This is a test template.</div>',
                methods: {
                    createdComponent() {
                        this.$super('createdComponent');
                    }
                }
            });

            shallowMount(ComponentFactory.build('root-component'));

            expect(createdData).toEqual(['root', 'overridden']);
        }
    );

    it(
        // eslint-disable-next-line max-len
        'correctly builds the super call stack when components in the beginning of the inheritance chain do not implement an overridden method when super is called from another super call',
        () => {
            let createdData = [];
            ComponentFactory.register('root-component', {
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
                    }
                }
            });
            ComponentFactory.override('root-component', {
                template: '<div>This is a test template.</div>',
                methods: {
                    getData() {
                        const data = this.$super('getData');
                        data.push('overridden');

                        createdData = data;

                        return data;
                    }
                }
            });
            ComponentFactory.override('root-component', {
                template: '<div>This is a test template.</div>'
            });
            ComponentFactory.override('root-component', {
                template: '<div>This is a test template.</div>',
                methods: {
                    createdComponent() {
                        this.$super('createdComponent');
                    }
                }
            });

            shallowMount(ComponentFactory.build('root-component'));

            expect(createdData).toEqual(['root', 'overridden']);
        }
    );

    it(
        'does not modify the override registry for extended components',
        () => {
            ComponentFactory.register('root-component', {
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
                    }
                }
            });
            ComponentFactory.override('root-component', {
                template: '<div>This is a test template.</div>',
                methods: {
                    getData() {
                        const data = this.$super('getData');
                        data.push('overridden');

                        return data;
                    }
                }
            });
            ComponentFactory.override('root-component', {
                template: '<div>This is a test template.</div>'
            });
            ComponentFactory.override('root-component', {
                template: '<div>This is a test template.</div>',
                methods: {
                    createdComponent() {
                        this.$super('createdComponent');
                    }
                }
            });

            shallowMount(ComponentFactory.build('root-component'));

            const expected = cloneDeep(ComponentFactory.getOverrideRegistry().get('root-component'));

            ComponentFactory.extend('child-component', 'root-component', {
                template: '<div>This is a test template.</div>'
            });
            ComponentFactory.build('child-component');

            const actual = cloneDeep(ComponentFactory.getOverrideRegistry().get('root-component'));

            expect(expected).toEqual(actual);
        }
    );

    it('overrides template and use these blocks', () => {
        ComponentFactory.register('component', {
            template: '<div>{% block test %}This is a test template.{% endblock %}</div>'
        });
        ComponentFactory.override('component', {
            template: '{% block test %}Override{% block new_block %}foo{% endblock %}{% endblock %}'
        });
        ComponentFactory.override('component', {
            template: '{% block new_block %}<div>Test</div>{% endblock %}'
        });

        const component = ComponentFactory.build('component');
        expect(component.template).toBe('<div>Override<div>Test</div></div>');
    });

    it('extends a component which is also an extension without a template', () => {
        ComponentFactory.register('root-component', {
            template: '{% block sw_settings_user_detail %}<h1>Foo</h1>{% endblock %}'
        });
        ComponentFactory.extend('child-component', 'root-component', {
            template: '{% block sw_settings_user_detail %}<h1>Test</h1>{% endblock %}'
        });
        ComponentFactory.extend('grandchild-component', 'child-component');

        const component = ComponentFactory.build('grandchild-component');
        expect(component.template).toBe('<h1>Test</h1>');
    });

    it('extends a component with wrapping template', () => {
        ComponentFactory.register('text-field-component', {
            template: '{% block text_field %}<input type="text">{% endblock %}'
        });
        ComponentFactory.extend('password-field-component', 'text-field-component', {
            template: '{% block password_field %}{% block text_field %}<input type="password">{% endblock %}{% endblock %}'
        });

        const component = ComponentFactory.build('password-field-component');
        expect(component.template).toBe('<input type="password">');
    });

    it('override should redeclare blocks if parent is used', () => {
        ComponentFactory.register('base-component', {
            // eslint-disable-next-line max-len
            template: '{% block base_component %}<div>{% block content %}This is the base content.{% endblock %}</div>{% endblock %}'
        });

        ComponentFactory.override('base-component', {
            template: '{% block content %}{% parent %} This is the inner override.{% endblock %}'
        });

        ComponentFactory.override('base-component', {
            template: '{% block base_component %}<div>This is the outer override. {% parent %}</div>{% endblock %}'
        });

        const component = ComponentFactory.build('base-component');
        // eslint-disable-next-line max-len
        const expected = '<div>This is the outer override. <div>This is the base content. This is the inner override.</div></div>';

        expect(component.template).toBe(expected);
    });

    it('allows to override nested blocks', () => {
        ComponentFactory.register('root-component', {
            // eslint-disable-next-line max-len
            template: '<div class="root-component">{% block outer_block %}{% block nested_block %}<div>I\'m nested</div>{% endblock %}{% endblock %}</div>'
        });

        ComponentFactory.override('root-component', {
            template:
                '{% block outer_block %}Overriding outer block {% parent %} {% endblock %}' +
                '{% block nested_block %}Overriding inner block {% parent %} {% endblock %}'
        });

        const component = ComponentFactory.build('root-component');
        // eslint-disable-next-line max-len
        const expected = '<div class="root-component">Overriding outer block Overriding inner block <div>I\'m nested</div>  </div>';

        expect(component.template).toEqual(expected);
    });

    it('allows to override nested blocks with parent call', () => {
        ComponentFactory.register('root-component', {
            // eslint-disable-next-line max-len
            template: '<div class="root-component">{% block outer_block %}Im the outer block {% block nested_block %}<div>I\'m nested</div>{% endblock %}{% endblock %}</div>'
        });

        ComponentFactory.override('root-component', {
            template:
                '{% block outer_block %}Overriding outer block {% parent %} {% endblock %}' +
                '{% block nested_block %}Overriding inner block {% parent %} {% endblock %}'
        });

        const component = ComponentFactory.build('root-component');
        // eslint-disable-next-line max-len
        const expected = '<div class="root-component">Overriding outer block Im the outer block Overriding inner block <div>I\'m nested</div>  </div>';

        expect(component.template).toEqual(expected);
    });

    it('ignores component overrides or extensions of components that are not registered', () => {
        ComponentFactory.override('override-without-register', {
            template: '{% block text_field %}<div>Not registered</div>{% endblock %}'
        });

        ComponentFactory.extend('extended-component', 'not-registered', {
            template: '{% block text_field %}<div>Not registered</div>{% endblock %}'
        });

        const overriden = ComponentFactory.build('override-without-register');
        const extended = ComponentFactory.build('extended-component');

        expect(overriden).toBe(false);
        expect(extended).toBe(false);
    });

    it('returns a component if it has no template but a render function', () => {
        ComponentFactory.override('not-registered-with-render-function', {
            render(h) { return h('div', {}, 'i was not registered'); }
        });

        ComponentFactory.register('with-render-function', {
            render(h) { return h('div', {}, 'registered component'); }
        });

        const overriden = ComponentFactory.build('not-registered-with-render-function');
        const registered = ComponentFactory.build('with-render-function');

        expect(overriden).toBe(false);
        expect(registered).not.toBe(false);
    });
});
