/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-return */

import { nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { _overridesMap, overrideComponentSetup } from 'src/app/adapter/composition-extension-system';
import { convert, defineExtendable, registerOptionsOverride } from './test-utils';

const name = 'sw-shim-this-proxy';

const createBase = () =>
    defineExtendable(
        {
            name,
            template: '<div class="result">{{ result }}</div><button ref="button" @click="run" />',
            props: { title: { type: String, default: 'Title' } },
            emits: ['saved'],
        },
        () => ({ public: { result: ref(''), run: () => {} } }),
    );

describe('src/app/adapter/options-composition-shim: this', () => {
    beforeEach(() => {
        _overridesMap.clear();
    });

    it('reads and writes previous state refs', () => {
        const previousState = { count: ref(42) };
        const result = convert(name, {
            methods: {
                read() {
                    return this.count;
                },
                write() {
                    this.count = 100;
                },
            },
        })(previousState, {});

        expect((result.read as () => number)()).toBe(42);
        (result.write as () => void)();
        expect(previousState.count.value).toBe(100);
    });

    it('resolves its own data before props and the previous state', () => {
        const result = convert(name, {
            data: () => ({ count: 999 }),
            methods: {
                read() {
                    return `${this.count as number} ${this.title as string}`;
                },
            },
        })({ count: ref(1) }, { title: 'Prop' });

        expect((result.read as () => string)()).toBe('999 Prop');
    });

    it('warns when reading a key that exists nowhere, including _-prefixed keys', () => {
        const result = convert(name, {
            methods: {
                read() {
                    return [
                        this.missing,
                        this._internal,
                    ];
                },
            },
        })({}, {});
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

        expect((result.read as () => unknown[])()).toEqual([
            undefined,
            undefined,
        ]);
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('Property "missing" not found in component state'));
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('Property "_internal" not found in component state'));
        warn.mockRestore();
    });

    it('logs an error when writing a prop or an unknown key', () => {
        const error = jest.spyOn(console, 'error').mockImplementation(() => {});
        const result = convert(name, {
            methods: {
                write() {
                    [
                        'title',
                        'unknown',
                    ].forEach((key) => {
                        try {
                            this[key] = 'value';
                        } catch {
                            // A proxy set trap that returns false throws in strict mode.
                        }
                    });
                },
            },
        })({}, { title: 'Prop' });

        (result.write as () => void)();

        expect(error).toHaveBeenCalledWith(
            '[Options API Shim] Cannot set property "title" - it is a component prop and is read-only.',
        );
        expect(error).toHaveBeenCalledWith(
            '[Options API Shim] Cannot set property "unknown" - property not found in component state',
        );
        error.mockRestore();
    });

    it('forwards $-prefixed keys to the component instance', async () => {
        registerOptionsOverride(name, {
            methods: {
                async run() {
                    this.result = `${this.$t('global.save') as string} ${this.$refs.button ? 'ref' : 'no ref'}`;
                    this.$emit('saved', this.title);
                    return await this.$nextTick(() => {
                        this.result += ' ticked';
                    });
                },
            },
        });

        const wrapper = mount(createBase());
        await wrapper.get('button').trigger('click');
        await nextTick();

        expect(wrapper.get('.result').text()).toBe('global.save ref ticked');
        expect(wrapper.emitted('saved')).toEqual([['Title']]);
    });

    describe('inject', () => {
        it('resolves the array form', () => {
            registerOptionsOverride(name, {
                inject: ['repositoryFactory'],
                created() {
                    this.result = this.repositoryFactory.name;
                },
            });

            const wrapper = mount(createBase(), { global: { provide: { repositoryFactory: { name: 'repository' } } } });

            expect(wrapper.get('.result').text()).toBe('repository');
        });

        it('resolves the object form with from and default', () => {
            registerOptionsOverride(name, {
                inject: {
                    renamed: 'acl',
                    fromKey: { from: 'feature' },
                    withDefault: { from: 'missing', default: 'fallback' },
                    sameName: {},
                },
                created() {
                    this.result = [
                        this.renamed,
                        this.fromKey,
                        this.withDefault,
                        this.sameName,
                    ].join(',');
                },
            });

            const wrapper = mount(createBase(), {
                global: { provide: { acl: 'acl', feature: 'feature', sameName: 'same' } },
            });

            expect(wrapper.get('.result').text()).toBe('acl,feature,fallback,same');
        });

        it('does not return injected values as override bindings', () => {
            const override = convert(name, { inject: ['acl'] });
            let result: Record<string, unknown> | null = null;
            overrideComponentSetup()(name, (previousState, props, context) => {
                result = override(previousState, props, context);

                return result;
            });

            mount(createBase(), { global: { provide: { acl: 'acl' } } });

            expect(result).toEqual({});
        });

        it('merges the inject config of mixins, the override winning on conflicts', () => {
            registerOptionsOverride(name, {
                mixins: [
                    { inject: { service: 'mixinService' }, mixins: [{ inject: ['deepService'] }] },
                ],
                inject: { service: 'ownService' },
                created() {
                    this.result = `${this.service as string} ${this.deepService as string}`;
                },
            });

            const wrapper = mount(createBase(), {
                global: { provide: { mixinService: 'mixin', ownService: 'own', deepService: 'deep' } },
            });

            expect(wrapper.get('.result').text()).toBe('own deep');
        });
    });
});
