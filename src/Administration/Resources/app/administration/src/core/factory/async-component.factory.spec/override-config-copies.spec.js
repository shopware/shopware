/**
 * @sw-package framework
 */

import { ComponentFactory, mount, setupComponentFactoryHooks } from './native-block-condition.fixtures';

function findInExtendsChain(config, optionName, key) {
    let current = config;
    while (current && typeof current === 'object') {
        if (current[optionName] && Object.prototype.hasOwnProperty.call(current[optionName], key)) {
            return current[optionName][key];
        }
        current = current.extends;
    }

    return undefined;
}

describe('core/factory/async-component.factory.ts - override configs shared between builds', () => {
    setupComponentFactoryHooks();

    let innerOverride;
    let middleOverride;
    let outerOverride;

    beforeEach(() => {
        ComponentFactory.register('test-component', {
            methods: {
                load() {
                    return 'base';
                },
            },
            template: '{% block content %}<div>Base</div>{% endblock %}',
        });

        innerOverride = {
            props: {
                innerFlag: {
                    type: Boolean,
                    required: false,
                    default: false,
                },
            },
            methods: {
                load() {
                    return `${this.innerHelper()} ${this.$super('load')}`;
                },
                innerHelper() {
                    return 'inner';
                },
            },
        };
        middleOverride = {
            computed: {
                middleValue() {
                    return 'middle';
                },
            },
        };
        outerOverride = {
            methods: {
                load() {
                    return `outer ${this.$super('load')}`;
                },
            },
        };

        ComponentFactory.override('test-component', innerOverride);
        ComponentFactory.override('test-component', middleOverride);
        ComponentFactory.override('test-component', outerOverride, 1);
    });

    it('keeps the methods and props of an inner override in a component built before other builds', async () => {
        const firstBuild = await ComponentFactory.build('test-component');
        await ComponentFactory.build('test-component');
        await ComponentFactory.build('test-component');
        await ComponentFactory.build('test-component');

        expect(typeof findInExtendsChain(firstBuild, 'methods', 'innerHelper')).toBe('function');
        expect(findInExtendsChain(firstBuild, 'props', 'innerFlag')).toEqual(expect.objectContaining({ type: Boolean }));
        expect(findInExtendsChain(firstBuild, 'computed', 'middleValue')).toEqual(expect.any(Function));
    });

    it('keeps the super call chain of a component built before other builds working', async () => {
        const firstBuild = await ComponentFactory.build('test-component');
        await ComponentFactory.build('test-component');
        await ComponentFactory.build('test-component');

        const instance = {
            _virtualCallStack: {},
            innerHelper: findInExtendsChain(firstBuild, 'methods', 'innerHelper'),
            ...firstBuild.methods,
        };

        expect(firstBuild.methods.load.call(instance)).toBe('outer inner base');
    });

    it('does not change the configs that were registered as overrides', async () => {
        await ComponentFactory.build('test-component');
        await ComponentFactory.build('test-component');

        expect(Object.keys(innerOverride.methods)).toEqual([
            'load',
            'innerHelper',
        ]);
        expect(Object.keys(innerOverride.props)).toEqual(['innerFlag']);
        expect(innerOverride).not.toHaveProperty('extends');
        expect(middleOverride).not.toHaveProperty('methods');
        expect(middleOverride).not.toHaveProperty('props');
        expect(Object.keys(outerOverride.methods)).toEqual(['load']);
        expect(outerOverride).not.toHaveProperty('props');
    });

    it('mounts an extension built before other builds of its base with the members of every override', async () => {
        ComponentFactory.extend('test-extension', 'test-component', {
            template: '{% block content %}<div class="result">{{ load() }} {{ innerFlag }}</div>{% endblock %}',
        });

        const extension = await ComponentFactory.build('test-extension');
        await ComponentFactory.build('test-component');
        await ComponentFactory.build('test-component');
        await ComponentFactory.build('test-component');

        const wrapper = mount(extension, { props: { innerFlag: true } });

        expect(wrapper.find('.result').text()).toBe('outer inner base true');
    });

    it('keeps class instances, getters and non-enumerable properties of override options', async () => {
        class Policy {
            allows() {
                return true;
            }
        }
        const policy = new Policy();
        let currentValue = 'value at build time';
        const provide = {};
        Object.defineProperty(provide, 'current', {
            enumerable: true,
            get: () => currentValue,
        });
        Object.defineProperty(provide, 'hidden', {
            enumerable: false,
            value: 'hidden value',
        });
        ComponentFactory.override('test-component', { policy, provide }, 2);

        const component = await ComponentFactory.build('test-component');
        currentValue = 'value at mount time';

        expect(component.policy).toBe(policy);
        expect(component.provide.current).toBe('value at mount time');
        expect(Object.getOwnPropertyDescriptor(component.provide, 'hidden').value).toBe('hidden value');
    });
});
