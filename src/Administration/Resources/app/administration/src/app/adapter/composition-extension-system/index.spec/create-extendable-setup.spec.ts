/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access */

import type { ComponentInternalInstance } from 'vue';
import { computed, defineComponent, getCurrentInstance, isRef, ref } from 'vue';
import { mount } from '@vue/test-utils';
import {
    _overridesMap,
    createExtendableSetup,
    getScriptSetupDataScope,
    overrideComponentSetup,
} from 'src/app/adapter/composition-extension-system';
import { defineExtendable } from './test-utils';

const name = 'sw-create-extendable-setup';

describe('src/app/adapter/composition-extension-system: createExtendableSetup()', () => {
    beforeEach(() => {
        _overridesMap.clear();
    });

    it('passes public bindings at the top level and private bindings under _private', () => {
        let received: Record<string, unknown> = {};

        overrideComponentSetup()(name, (previousState) => {
            received = previousState;

            return {};
        });

        mount(
            defineExtendable({ name, template: '<div />' }, () => ({
                public: { title: ref('public') },
                private: { internalId: ref('private') },
            })),
        );

        expect(Object.keys(received)).toEqual([
            '_private',
            'title',
        ]);
        expect((received.title as { value: string }).value).toBe('public');
        expect(Object.keys(received._private as object)).toEqual(['internalId']);
    });

    it('lets an override replace a private binding', () => {
        overrideComponentSetup()(name, (previousState) => ({
            internalId: ref(`${previousState._private.internalId.value as string}!`),
        }));

        const wrapper = mount(
            defineExtendable({ name, template: '<div>{{ internalId }}</div>' }, () => ({
                private: { internalId: ref('private') },
            })),
        );

        expect(wrapper.text()).toBe('private!');
    });

    it('returns one ref per binding, without evaluating computeds', () => {
        const evaluate = jest.fn(() => 1);
        let result: Record<string, unknown> = {};

        mount(
            defineComponent({
                template: '<div />',
                setup: (props, context) => {
                    result = createExtendableSetup({ props, context, name }, () => ({
                        public: { count: ref(1), doubled: computed(evaluate), save: () => {} },
                    }));

                    return {};
                },
            }),
        );

        expect(Object.keys(result)).toEqual([
            'count',
            'doubled',
            'save',
        ]);
        expect(Object.values(result).every(isRef)).toBe(true);
        expect(evaluate).not.toHaveBeenCalled();
    });

    it('registers the reactive setup state as data scope of the instance', () => {
        let instance: ComponentInternalInstance | null = null;

        mount(
            defineComponent({
                template: '<div />',
                setup: (props, context) => {
                    instance = getCurrentInstance();

                    return createExtendableSetup({ props, context, name }, () => ({ public: { count: ref(3) } }));
                },
            }),
        );

        expect(getScriptSetupDataScope(instance!)?.count).toBe(3);
    });

    it('throws when the setup returns neither public nor private bindings', () => {
        expect(() => createExtendableSetup({ props: {}, context: {}, name }, () => ({}))).toThrow(
            `[${name}] The original setup function for the originalComponent component must return at least one public or private property.`,
        );
    });

    it('logs an error for top-level keys other than public and private', () => {
        const error = jest.spyOn(console, 'error').mockImplementation(() => {});

        createExtendableSetup({ props: {}, context: {}, name }, () => ({ public: {}, other: {} }) as never);

        expect(error).toHaveBeenCalledWith(
            `[${name}] The original setup function for the originalComponent component returned an unexpected value. Only public and private properties at first level are allowed.`,
        );
        error.mockRestore();
    });

    it('works outside of a component instance', () => {
        overrideComponentSetup()(name, () => ({ count: ref(2) }));

        const result = createExtendableSetup({ props: {}, context: {}, name }, () => ({
            public: { count: ref(1) },
        }));

        expect(result.count.value).toBe(2);
    });
});
