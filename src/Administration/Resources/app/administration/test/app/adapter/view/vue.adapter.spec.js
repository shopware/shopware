import { shallowMount, config } from '@vue/test-utils';
import VueAdapter from 'src/app/adapter/view/vue.adapter';
import ViewAdapter from 'src/core/adapter/view.adapter';
import ComponentFactory from 'src/core/factory/component.factory';

jest.mock('vue-i18n', () => (function MockI18n() {}));
Shopware.Service().register('localeHelper', () => {
    return {
        setLocaleWithId: jest.fn()
    };
});

describe('app/adapter/view/vue.adapter.js', () => {
    let vueAdapter;

    beforeEach(() => {
        // delete global $router and $routes mocks
        delete config.mocks.$router;
        delete config.mocks.$route;

        // mock vue adapter
        vueAdapter = new VueAdapter({
            getContainer: () => ({
                component: '',
                locale: { getLocaleRegistry: () => [], getLastKnownLocale: () => 'en-GB' }
            })
        });

        // mock localeHelper Service
        Shopware.Service('localeHelper').setLocaleWithId.mockReset();
    });

    afterEach(() => {
        ComponentFactory.markComponentTemplatesAsNotResolved();
    });

    it('should be an class', async () => {
        const type = typeof VueAdapter;
        expect(type).toEqual('function');
    });

    it('should extends the view adapter', async () => {
        const isInstanceOfViewAdapter = VueAdapter.prototype instanceof ViewAdapter;
        expect(isInstanceOfViewAdapter).toBeTruthy();
    });

    it('initLocales should call setLocaleFromuser', async () => {
        // Mock function
        vueAdapter.setLocaleFromUser = jest.fn();

        vueAdapter.initLocales({
            subscribe: () => {},
            dispatch: () => {},
            state: { session: { currentLocale: 'en-GB' } }
        });

        expect(vueAdapter.setLocaleFromUser).toHaveBeenCalled();
    });

    it('setLocaleFromUser should not set the user when user does not exists', async () => {
        vueAdapter.setLocaleFromUser({
            state: { session: { currentUser: null } }
        });

        expect(Shopware.Service('localeHelper').setLocaleWithId).not.toHaveBeenCalled();
    });

    it('setLocaleFromUser should set the user when user does not exists', async () => {
        vueAdapter.setLocaleFromUser({
            state: { session: { currentUser: { localeId: '12345' } } }
        });

        expect(Shopware.Service('localeHelper').setLocaleWithId).toHaveBeenCalled();
    });

    it('setLocaleFromUser should call the service with the user id from the store', async () => {
        const expectedId = '12345678';

        vueAdapter.setLocaleFromUser({
            state: { session: { currentUser: { localeId: expectedId } } }
        });

        expect(Shopware.Service('localeHelper').setLocaleWithId).toHaveBeenCalledWith(expectedId);
    });

    it('should resolve mixins by explicit Mixin get by name call', () => {
        Shopware.Mixin.register('foo1', {
            methods: {
                fooBar() {
                    return this.title;
                }
            }
        });

        Shopware.Component.register('test-component1', {
            template: '<div></div>',
            name: 'test-component1',
            data() {
                return {
                    title: 'testComponent'
                };
            },
            mixins: [
                Shopware.Mixin.getByName('foo1')
            ],
            methods: {
                bar() {}
            }
        });

        const buildComp = vueAdapter.createComponent('test-component1');
        let wrapper = shallowMount(buildComp);

        expect(buildComp.sealedOptions.methods.fooBar).toBeDefined();
        expect(buildComp.sealedOptions.methods.bar).toBeDefined();
        expect(wrapper.vm.fooBar()).toBe('testComponent');

        Shopware.Component.override('test-component1', {
            data() {
                return {
                    title: 'testComponentOverride'
                };
            },
            methods: {
                buz() {}
            }
        });

        const buildOverrideComp = vueAdapter.createComponent('test-component1');
        wrapper = shallowMount(buildOverrideComp);

        expect(buildOverrideComp.sealedOptions.methods.fooBar).toBeDefined();
        expect(buildOverrideComp.sealedOptions.methods.bar).toBeDefined();
        expect(buildOverrideComp.sealedOptions.methods.buz).toBeDefined();
        expect(wrapper.vm.fooBar()).toBe('testComponentOverride');
    });

    it('should resolve mixins by string', () => {
        Shopware.Mixin.register('foo', {
            methods: {
                fooBar() {
                    return this.title;
                }
            }
        });

        Shopware.Component.register('test-component', {
            template: '<div></div>',
            name: 'test-component',
            data() {
                return {
                    title: 'testComponent'
                };
            },
            mixins: [
                'foo'
            ],
            methods: {
                bar() {}
            }
        });

        const buildComp = vueAdapter.createComponent('test-component');
        let wrapper = shallowMount(buildComp);

        expect(buildComp.sealedOptions.methods.fooBar).toBeDefined();
        expect(buildComp.sealedOptions.methods.bar).toBeDefined();
        expect(wrapper.vm.fooBar()).toBe('testComponent');

        Shopware.Component.override('test-component', {
            data() {
                return {
                    title: 'testComponentOverride'
                };
            },
            methods: {
                buz() {}
            }
        });

        const buildOverrideComp = vueAdapter.createComponent('test-component');
        wrapper = shallowMount(buildOverrideComp);

        expect(buildOverrideComp.sealedOptions.methods.fooBar).toBeDefined();
        expect(buildOverrideComp.sealedOptions.methods.bar).toBeDefined();
        expect(buildOverrideComp.sealedOptions.methods.buz).toBeDefined();
        expect(wrapper.vm.fooBar()).toBe('testComponentOverride');
    });

    it('should resolve mixins for component in combination with overrides', () => {
        Shopware.Mixin.register('foo-with-data', {
            data() {
                return {
                    sortBy: null
                };
            },
            methods: {
                fooBar() {
                    return this.sortBy;
                }
            }
        });

        Shopware.Component.register('test-component-foobar-with-mixin', {
            template: '<div></div>',
            name: 'test-component',
            data() {
                return {
                    sortBy: 'date'
                };
            },
            mixins: [
                'foo-with-data'
            ],
            methods: {
                bar() {},
                fooBar() {
                    return this.sortBy;
                }
            }
        });

        const buildComp = vueAdapter.createComponent('test-component-foobar-with-mixin');
        let wrapper = shallowMount(buildComp);

        expect(buildComp.sealedOptions.methods.fooBar).toBeDefined();
        expect(buildComp.sealedOptions.methods.bar).toBeDefined();
        expect(wrapper.vm.fooBar()).toBe('date');

        // add an override to the component
        Shopware.Component.override('test-component-foobar-with-mixin', {});

        const buildOverrideComp = vueAdapter.createComponent('test-component-foobar-with-mixin');
        wrapper = shallowMount(buildOverrideComp);

        expect(buildOverrideComp.sealedOptions.methods.fooBar).toBeDefined();
        expect(buildOverrideComp.sealedOptions.methods.bar).toBeDefined();
        expect(wrapper.vm.fooBar()).toBe('date');
    });

    it('should extend mixins', () => {
        Shopware.Mixin.register('swFoo', {
            methods: {
                fooBar() {
                    return this.title;
                }
            }
        });

        Shopware.Mixin.register('swBar', {
            methods: {
                biz() {
                    return this.title;
                },
                buz() {
                    return 'mixin';
                }
            }
        });

        Shopware.Component.register('extendable-component', {
            template: '{% block foo %}<div>aaaaa</div>{% endblock %}',
            name: 'extendable-component',
            data() {
                return {
                    title: 'testComponent'
                };
            },
            mixins: [
                'swFoo'
            ],
            methods: {
                bar() {}
            }
        });

        Shopware.Component.extend('sw-test-component-extended', 'extendable-component', {
            template: '{% block foo %}<div>bbbbb</div>{% endblock %}',
            mixins: [
                'swBar'
            ],
            data() {
                return {
                    title: 'testComponentExtended'
                };
            },
            methods: {
                buz() {
                    return 'component';
                }
            }
        });

        const buildExtendedComponent = vueAdapter.createComponent('sw-test-component-extended');
        const wrapper = shallowMount(buildExtendedComponent);

        expect(buildExtendedComponent.sealedOptions.methods.fooBar).toBeDefined();
        expect(buildExtendedComponent.sealedOptions.methods.bar).toBeDefined();
        expect(buildExtendedComponent.sealedOptions.methods.biz).toBeDefined();
        expect(buildExtendedComponent.sealedOptions.methods.buz).toBeDefined();
        expect(wrapper.vm.fooBar()).toBe('testComponentExtended');
        expect(wrapper.vm.buz()).toBe('component');
    });

    it('should allow multi-inheritance with multiple mixins and lifecycle hooks are only executed once', () => {
        const lifecycleSpy = jest.fn();
        Shopware.Mixin.register('first-mixin', {
            created() {
                lifecycleSpy();
            },
            methods: {
                foo() { return 'foo'; }
            }
        });

        Shopware.Mixin.register('second-mixin', {
            methods: {
                bar() { return 'bar'; }
            }
        });

        Shopware.Component.register('base-component', {
            template: '<div class="base-component"></div>'
        });

        Shopware.Component.override('base-component', {
            mixins: ['first-mixin']
        });

        Shopware.Component.override('base-component', {
            mixins: ['second-mixin', 'first-mixin']
        });

        const buildExtendedComponent = vueAdapter.createComponent('base-component');
        const wrapper = shallowMount(buildExtendedComponent);

        expect(buildExtendedComponent.sealedOptions.methods.foo).toBeDefined();
        expect(buildExtendedComponent.sealedOptions.methods.bar).toBeDefined();
        expect(wrapper.vm.foo()).toBe('foo');
        expect(wrapper.vm.bar()).toBe('bar');

        expect(lifecycleSpy).toBeCalledTimes(1);
    });

    it('should build & create a vue.js component', () => {
        const componentDefinition = {
            name: 'sw-foo',

            render(h) {
                return h('div', {
                    class: {
                        'sw-foo': true
                    }
                }, ['Some text']);
            }
        };

        const component = vueAdapter.buildAndCreateComponent(componentDefinition);
        const mountedComponent = shallowMount(component);
        expect(mountedComponent.vm).toBeTruthy();
    });
});
