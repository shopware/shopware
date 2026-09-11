/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-return */

import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import { convertOptionsApiOverrideToCompositionApi } from 'src/app/adapter/options-composition-shim';

import { ref } from 'vue';

import { convertWithSilencedWarning } from './fixtures';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
    });
    describe('createThisProxy():', () => {
        it('should resolve this.propertyName to previousState ref values', () => {
            const previousState = {
                count: ref(42),
                name: ref('test'),
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    getCount() {
                        return this.count;
                    },
                    getName() {
                        return this.name;
                    },
                },
            });

            const result = overrideFn(previousState, {}) as Record<string, any>;

            expect(result.getCount()).toBe(42);
            expect(result.getName()).toBe('test');
        });

        it('should allow setting ref values via this.propertyName', () => {
            const previousState = {
                count: ref(1),
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    setCount() {
                        this.count = 100;
                    },
                },
            });

            const result = overrideFn(previousState, {}) as Record<string, any>;
            result.setCount();

            expect(previousState.count.value).toBe(100);
        });

        it('should resolve props via this', () => {
            const previousState = {};
            const props = { title: 'Hello' };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    getTitle() {
                        return this.title;
                    },
                },
            });

            const result = overrideFn(previousState, props) as Record<string, any>;

            expect(result.getTitle()).toBe('Hello');
        });

        it('should prioritize local state over previousState', () => {
            const previousState = {
                count: ref(1),
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { count: 999 };
                },
                methods: {
                    getCount() {
                        return this.count;
                    },
                },
            });

            const result = overrideFn(previousState, {}) as Record<string, any>;

            expect(result.getCount()).toBe(999);
        });

        it('should warn about accessing undefined properties', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const previousState = {};

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                methods: {
                    accessUndefined() {
                        return this.nonExistentProp;
                    },
                },
            });

            const result = overrideFn(previousState, {}) as Record<string, any>;
            const value = result.accessUndefined();

            expect(value).toBeUndefined();
            expect(consoleWarn).toHaveBeenCalledWith(expect.stringContaining('Property "nonExistentProp" not found'));

            consoleWarn.mockRestore();
        });

        it('should not warn about Vue instance properties starting with $', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const previousState = {};

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                methods: {
                    accessVueProperty() {
                        return this.$route;
                    },
                },
            });

            const result = overrideFn(previousState, {}) as Record<string, any>;
            result.accessVueProperty();

            // Filter out the deprecation warning to check only property warnings
            const propertyWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('not found in component state'),
            );
            expect(propertyWarnings).toHaveLength(0);

            consoleWarn.mockRestore();
        });

        it('should warn about unknown properties starting with _', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const previousState = {};

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                methods: {
                    accessInternal() {
                        return this._internal;
                    },
                },
            });

            const result = overrideFn(previousState, {}) as Record<string, any>;
            result.accessInternal();

            // Filter out the deprecation warning to check only property warnings
            const propertyWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('not found in component state'),
            );
            expect(propertyWarnings).toHaveLength(1);
            expect(propertyWarnings[0][0]).toContain('"_internal"');

            consoleWarn.mockRestore();
        });

        it('should retain instance fields assigned by an override', () => {
            const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const previousState = {};

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                methods: {
                    setUnknown() {
                        try {
                            this.unknownProp = 123;
                        } catch {
                            // Proxy set returning false throws TypeError in strict mode
                        }
                    },
                },
            });

            const result = overrideFn(previousState, {}) as Record<string, any>;
            result.setUnknown();

            expect(result.unknownProp).toBe(123);
            expect(consoleError).not.toHaveBeenCalled();

            consoleError.mockRestore();
            consoleWarn.mockRestore();
        });
    });
});
