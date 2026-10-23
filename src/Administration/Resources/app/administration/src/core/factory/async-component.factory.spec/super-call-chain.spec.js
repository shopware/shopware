/**
 * @sw-package framework
 */

import { ComponentFactory, mount, setupComponentFactoryHooks } from './native-block-condition.fixtures';

const template = '{% block content %}<div>Base</div>{% endblock %}';

function wait(milliseconds) {
    return new Promise((resolve) => {
        setTimeout(resolve, milliseconds);
    });
}

describe('core/factory/async-component.factory.ts - $super call chain', () => {
    setupComponentFactoryHooks();

    it('reaches an override method across two overrides that do not define it', async () => {
        ComponentFactory.register('test-component', { template });
        ComponentFactory.override('test-component', {
            methods: {
                label() {
                    return 'first';
                },
            },
        });
        ComponentFactory.override('test-component', {
            computed: {
                unrelatedA() {
                    return 'a';
                },
            },
        });
        ComponentFactory.override('test-component', {
            computed: {
                unrelatedB() {
                    return 'b';
                },
            },
        });
        ComponentFactory.override('test-component', {
            methods: {
                label() {
                    return `last > ${this.$super('label')}`;
                },
            },
        });

        const wrapper = mount(await ComponentFactory.build('test-component'));

        expect(wrapper.vm.label()).toBe('last > first');
    });

    it('keeps overlapping async calls on their own chain', async () => {
        ComponentFactory.register('test-component', {
            methods: {
                async load(tag) {
                    await wait(5);
                    return `base(${tag})`;
                },
            },
            template,
        });
        ComponentFactory.override('test-component', {
            methods: {
                async load(tag) {
                    await wait(3);
                    return `inner > ${await this.$super('load', tag)}`;
                },
            },
        });
        ComponentFactory.override('test-component', {
            methods: {
                async load(tag) {
                    await wait(1);
                    return `outer > ${await this.$super('load', tag)}`;
                },
            },
        });

        const wrapper = mount(await ComponentFactory.build('test-component'));
        const results = await Promise.all([
            wrapper.vm.load(1),
            wrapper.vm.load(2),
            wrapper.vm.load(3),
        ]);

        expect(results).toEqual([
            'outer > inner > base(1)',
            'outer > inner > base(2)',
            'outer > inner > base(3)',
        ]);
    });

    it('calls the layer below from a mixin of an override', async () => {
        ComponentFactory.register('test-component', {
            methods: {
                label() {
                    return 'base';
                },
            },
            template,
        });
        ComponentFactory.override('test-component', {
            mixins: [
                {
                    methods: {
                        label() {
                            return `mixin > ${this.$super('label')}`;
                        },
                    },
                },
            ],
        });

        const wrapper = mount(await ComponentFactory.build('test-component'));

        expect(wrapper.vm.label()).toBe('mixin > base');
    });

    it('resolves $super of another method below the calling override', async () => {
        ComponentFactory.register('test-component', {
            methods: {
                summary() {
                    return 'base summary';
                },
                label() {
                    return 'base label';
                },
            },
            template,
        });
        ComponentFactory.override('test-component', {
            methods: {
                label() {
                    return `inner label > ${this.$super('label')}`;
                },
            },
        });
        ComponentFactory.override('test-component', {
            methods: {
                summary() {
                    return `outer summary uses ${this.$super('label')}`;
                },
                label() {
                    return 'outer label';
                },
            },
        });

        const wrapper = mount(await ComponentFactory.build('test-component'));

        expect(wrapper.vm.summary()).toBe('outer summary uses inner label > base label');
    });

    it('keeps a component built earlier working after later builds', async () => {
        ComponentFactory.register('test-component', {
            methods: {
                load() {
                    return 'base';
                },
            },
            template: '{% block content %}<div class="result">{{ load() }}</div>{% endblock %}',
        });
        ComponentFactory.override('test-component', {
            methods: {
                load() {
                    return `${this.innerHelper()} ${this.$super('load')}`;
                },
                innerHelper() {
                    return 'inner';
                },
            },
        });
        ComponentFactory.override('test-component', {
            computed: {
                middleValue() {
                    return 'middle';
                },
            },
        });
        ComponentFactory.override(
            'test-component',
            {
                methods: {
                    load() {
                        return `outer ${this.$super('load')}`;
                    },
                },
            },
            1,
        );

        const firstBuild = await ComponentFactory.build('test-component');
        await ComponentFactory.build('test-component');
        await ComponentFactory.build('test-component');

        expect(mount(firstBuild).find('.result').text()).toBe('outer inner base');
    });

    it('leaves the configs passed to register, extend and override unchanged', async () => {
        const baseMethods = {
            load() {
                return 'base';
            },
        };
        const extensionMethods = {
            own() {
                return 'own';
            },
        };
        const overrideMethods = {
            helper() {
                return 'helper';
            },
        };
        ComponentFactory.register('test-component', { methods: baseMethods, template });
        ComponentFactory.extend('test-extension', 'test-component', { methods: extensionMethods });
        ComponentFactory.override('test-extension', { methods: overrideMethods });
        ComponentFactory.override('test-extension', {
            methods: {
                load() {
                    return `override > ${this.$super('load')}`;
                },
            },
        });

        await ComponentFactory.build('test-extension');
        const wrapper = mount(await ComponentFactory.build('test-extension'));

        expect(wrapper.vm.load()).toBe('override > base');
        expect(Object.keys(baseMethods)).toEqual(['load']);
        expect(Object.keys(extensionMethods)).toEqual(['own']);
        expect(Object.keys(overrideMethods)).toEqual(['helper']);
    });
});
