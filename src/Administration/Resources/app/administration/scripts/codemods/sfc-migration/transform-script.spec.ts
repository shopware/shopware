import {
    transformData,
    transformComputed,
    transformMethods,
    transformWatch,
    transformInject,
    transformLifecycleHooks,
    detectUnsupportedFeatures,
} from './transform-script';

describe('scripts/codemods/sfc-migration/transform-script', () => {
    describe('transformData', () => {
        it('converts a primitive string value to a ref()', () => {
            const lines = transformData({ title: "'Default Title'" });

            expect(lines).toContain("const title = ref('Default Title');");
        });

        it('converts a primitive number value to a ref()', () => {
            const lines = transformData({ count: '0' });

            expect(lines).toContain('const count = ref(0);');
        });

        it('converts a boolean value to a ref()', () => {
            const lines = transformData({ isLoading: 'false' });

            expect(lines).toContain('const isLoading = ref(false);');
        });

        it('converts an object value to a reactive()', () => {
            const lines = transformData({ settings: '{ theme: "dark", size: 12 }' });

            expect(lines.join('\n')).toContain('const settings = reactive(');
            expect(lines.join('\n')).toContain('theme: "dark"');
        });

        it('converts an array value to a ref()', () => {
            const lines = transformData({ items: '[]' });

            expect(lines).toContain('const items = ref([]);');
        });

        it('converts null value to a ref()', () => {
            const lines = transformData({ selectedId: 'null' });

            expect(lines).toContain('const selectedId = ref(null);');
        });

        it('produces one declaration per data property', () => {
            const lines = transformData({
                title: "'hello'",
                count: '0',
                isLoading: 'false',
            });

            expect(lines).toHaveLength(3);
        });

        it('returns an empty array for empty data', () => {
            const lines = transformData({});

            expect(lines).toEqual([]);
        });
    });

    describe('transformComputed', () => {
        it('converts a getter-only function to a computed()', () => {
            const lines = transformComputed({
                description: "() => `This is: ${title.value}`",
            });

            expect(lines.join('\n')).toContain('const description = computed(');
            expect(lines.join('\n')).toContain("() => `This is: ${title.value}`");
        });

        it('converts a getter+setter pair to computed({ get, set })', () => {
            const lines = transformComputed({
                label: { get: '() => title.value', set: '(val) => { title.value = val; }' },
            });

            const joined = lines.join('\n');
            expect(joined).toContain('const label = computed({');
            expect(joined).toContain('get:');
            expect(joined).toContain('set:');
        });

        it('produces one declaration per computed property', () => {
            const lines = transformComputed({
                foo: '() => 1',
                bar: '() => 2',
            });

            expect(lines).toHaveLength(2);
        });

        it('returns an empty array for empty computed object', () => {
            expect(transformComputed({})).toEqual([]);
        });
    });

    describe('transformMethods', () => {
        it('converts a regular method to a const arrow function', () => {
            const lines = transformMethods({
                onSave: "() => { isLoading.value = true; }",
            });

            expect(lines.join('\n')).toContain('const onSave =');
        });

        it('converts an async method to an async arrow function', () => {
            const lines = transformMethods({
                loadData: 'async () => { const result = await fetch("/api"); }',
            });

            expect(lines.join('\n')).toContain('const loadData = async');
        });

        it('substitutes this.xxx references with the bare variable name', () => {
            const lines = transformMethods({
                toggle: '() => { this.isLoading = !this.isLoading; }',
            });

            const joined = lines.join('\n');
            expect(joined).not.toContain('this.isLoading');
            expect(joined).toContain('isLoading');
        });

        it('preserves this.$super() calls with a TODO migration comment', () => {
            const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const lines = transformMethods({
                save: "() => { this.$super('save'); }",
            });

            const joined = lines.join('\n');
            expect(joined).toContain('$super');
            expect(joined).toContain('TODO');

            warnSpy.mockRestore();
        });

        it('logs a warning when this.$super() is encountered', () => {
            const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});

            transformMethods({ save: "() => { this.$super('save'); }" });

            expect(warnSpy).toHaveBeenCalledWith(
                expect.stringContaining('$super'),
            );

            warnSpy.mockRestore();
        });

        it('produces one declaration per method', () => {
            const lines = transformMethods({
                foo: '() => {}',
                bar: '() => {}',
                baz: '() => {}',
            });

            expect(lines).toHaveLength(3);
        });

        it('returns an empty array for empty methods object', () => {
            expect(transformMethods({})).toEqual([]);
        });
    });

    describe('transformWatch', () => {
        it('converts a string-key shorthand watcher to a watch() call', () => {
            const lines = transformWatch({
                count: '(newVal) => { if (newVal > 10) title.value = "Limit"; }',
            });

            const joined = lines.join('\n');
            expect(joined).toContain('watch(');
            expect(joined).toContain('count');
        });

        it('converts a watcher with options (deep, immediate)', () => {
            const lines = transformWatch({
                count: {
                    handler: '(newVal) => {}',
                    deep: true,
                    immediate: true,
                },
            });

            const joined = lines.join('\n');
            expect(joined).toContain('watch(');
            expect(joined).toContain('deep: true');
            expect(joined).toContain('immediate: true');
        });

        it('skips dot-notation paths and logs a warning', () => {
            const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const lines = transformWatch({
                'product.name': '(val) => {}',
            });

            expect(lines).toHaveLength(0);
            expect(warnSpy).toHaveBeenCalledWith(
                expect.stringContaining('product.name'),
            );

            warnSpy.mockRestore();
        });

        it('produces one watch() call per supported watcher', () => {
            const lines = transformWatch({
                count: '() => {}',
                title: '() => {}',
            });

            expect(lines).toHaveLength(2);
        });

        it('returns an empty array for empty watch object', () => {
            expect(transformWatch({})).toEqual([]);
        });
    });

    describe('transformInject', () => {
        it('converts array inject form to inject() calls', () => {
            const lines = transformInject(['repositoryFactory', 'acl']);

            expect(lines).toContain("const repositoryFactory = inject('repositoryFactory');");
            expect(lines).toContain("const acl = inject('acl');");
        });

        it('converts object inject form with from alias to inject() call', () => {
            const lines = transformInject({
                myService: { from: 'repositoryFactory' },
            });

            expect(lines.join('\n')).toContain("inject('repositoryFactory')");
            expect(lines.join('\n')).toContain('myService');
        });

        it('converts object inject form with a default value', () => {
            const lines = transformInject({
                locale: { from: 'locale', default: "'en-GB'" },
            });

            expect(lines.join('\n')).toContain("inject('locale', 'en-GB')");
        });

        it('produces one declaration per injected service', () => {
            const lines = transformInject(['a', 'b', 'c']);

            expect(lines).toHaveLength(3);
        });

        it('returns an empty array for empty inject array', () => {
            expect(transformInject([])).toEqual([]);
        });

        it('returns an empty array for empty inject object', () => {
            expect(transformInject({})).toEqual([]);
        });
    });

    describe('transformLifecycleHooks', () => {
        it('converts mounted to onMounted', () => {
            const lines = transformLifecycleHooks({
                mounted: '() => { count.value = 0; }',
            });

            expect(lines.join('\n')).toContain('onMounted(');
        });

        it('converts created to onMounted with a migration comment', () => {
            const lines = transformLifecycleHooks({
                created: '() => { init(); }',
            });

            const joined = lines.join('\n');
            expect(joined).toContain('onMounted(');
            expect(joined).toContain('created');
        });

        it('converts beforeDestroy to onBeforeUnmount', () => {
            const lines = transformLifecycleHooks({
                beforeDestroy: '() => { cleanup(); }',
            });

            expect(lines.join('\n')).toContain('onBeforeUnmount(');
        });

        it('converts destroyed to onUnmounted', () => {
            const lines = transformLifecycleHooks({
                destroyed: '() => {}',
            });

            expect(lines.join('\n')).toContain('onUnmounted(');
        });

        it('converts beforeMount to onBeforeMount', () => {
            const lines = transformLifecycleHooks({
                beforeMount: '() => {}',
            });

            expect(lines.join('\n')).toContain('onBeforeMount(');
        });

        it('converts updated to onUpdated', () => {
            const lines = transformLifecycleHooks({
                updated: '() => {}',
            });

            expect(lines.join('\n')).toContain('onUpdated(');
        });

        it('converts multiple hooks in one call', () => {
            const lines = transformLifecycleHooks({
                mounted: '() => {}',
                beforeDestroy: '() => {}',
            });

            const joined = lines.join('\n');
            expect(joined).toContain('onMounted(');
            expect(joined).toContain('onBeforeUnmount(');
        });

        it('returns an empty array for empty hooks object', () => {
            expect(transformLifecycleHooks({})).toEqual([]);
        });
    });

    describe('detectUnsupportedFeatures', () => {
        it('returns an empty array for a component using only supported features', () => {
            const jsContent = `
Shopware.Component.register('sw-simple', {
    template,
    inject: ['acl'],
    data() { return { count: 0 }; },
    computed: { doubled() { return this.count * 2; } },
    methods: { increment() { this.count++; } },
    mounted() {},
});`;

            expect(detectUnsupportedFeatures(jsContent)).toEqual([]);
        });

        it('detects mixins as an unsupported feature', () => {
            const jsContent = `
Shopware.Component.register('sw-list', {
    template,
    mixins: [Shopware.Mixin.getByName('notification')],
    data() { return {}; },
});`;

            expect(detectUnsupportedFeatures(jsContent)).toContain('mixins');
        });

        it('detects render() function as an unsupported feature', () => {
            const jsContent = `
Shopware.Component.register('sw-render', {
    render() { return h('div'); },
});`;

            expect(detectUnsupportedFeatures(jsContent)).toContain('render function');
        });

        it('detects Options API extends as an unsupported feature', () => {
            const jsContent = `
Shopware.Component.extend('sw-child', 'sw-parent', {
    template,
    data() { return {}; },
});`;

            expect(detectUnsupportedFeatures(jsContent)).toContain('extends');
        });

        it('detects multiple unsupported features at once', () => {
            const jsContent = `
Shopware.Component.register('sw-complex', {
    template,
    mixins: [someMixin],
    render() { return h('div'); },
});`;

            const blockers = detectUnsupportedFeatures(jsContent);
            expect(blockers).toContain('mixins');
            expect(blockers).toContain('render function');
        });

        it('returns an empty array for an empty component definition', () => {
            const jsContent = `
Shopware.Component.register('sw-empty', {
    template,
});`;

            expect(detectUnsupportedFeatures(jsContent)).toEqual([]);
        });
    });
});
