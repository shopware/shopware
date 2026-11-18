/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';
import type { Component } from 'vue';
import { MtTextField } from '@shopware-ag/meteor-component-library';
import gridPattern from '../index';

const containerStub = { template: '<div><slot /></div>' };
const buttonStub = {
    props: ['disabled'],
    emits: ['click'],
    template: '<button :disabled="disabled" @click="$emit(\'click\', $event)"><slot /></button>',
};
const actionStub = {
    props: ['disabled'],
    emits: ['select'],
    template: '<button :disabled="disabled" @click="$emit(\'select\')"><slot /></button>',
};

/** @private */
export function layout(value: string) {
    const entries = value.split('~');
    return Object.fromEntries(
        entries.map((entry, index) => {
            const [breakpoint, configuration] = index === 0 ? ['xs', entry] : entry.split('=');
            const [widths, rows] = configuration.split('|');
            return [
                breakpoint,
                {
                    columns: widths.split(' ').map((width) => {
                        const [type, encoded] = width.split(':');
                        if (type === 'auto') return { type };
                        const raw = decodeURIComponent(encoded ?? '');
                        let fixtureValue: string | number = raw;
                        try {
                            const parsed: unknown = JSON.parse(raw);
                            if (typeof parsed === 'number' && Number.isSafeInteger(parsed) && parsed > 0) {
                                fixtureValue = parsed;
                            }
                        } catch {
                            // Draft input is not necessarily JSON.
                        }
                        return { type, value: fixtureValue };
                    }),
                    areas: rows.split(';').map((row) => row.split(' ')),
                },
            ];
        }),
    );
}

/** @private */
export function createWrapper(props: Record<string, unknown> = {}, realComponents = false) {
    return mount(gridPattern, {
        shallow: !realComponents,
        attachTo: document.body,
        props: { allowEdit: true, ...props },
        global: {
            directives: {
                draggable: realComponents
                    ? Shopware.Directive.getByName('draggable')!
                    : {
                          mounted(
                              element: HTMLElement,
                              binding: { value: { data: number; onDragStart: (config: { data: number }) => void } },
                          ) {
                              element.ondragstart = () => binding.value.onDragStart(binding.value);
                          },
                      },
                droppable: {},
            },
            stubs: {
                'sw-button-group': containerStub,
                'mt-collapsible': containerStub,
                'mt-collapsible-content': containerStub,
                'mt-collapsible-trigger': containerStub,
                'mt-button': buttonStub,
                'mt-link': buttonStub,
                'mt-dropdown-menu-root': containerStub,
                'mt-dropdown-menu-trigger': containerStub,
                'mt-dropdown-menu-portal': containerStub,
                'mt-action-menu': containerStub,
                'mt-action-menu-item': actionStub,
                'mt-icon': true,
                'mt-text': containerStub,
                'mt-modal-root': { props: ['isOpen'], template: '<div v-if="isOpen"><slot /></div>' },
                'mt-modal': { template: '<div><slot /><slot name="footer" /></div>' },
                'mt-select': {
                    props: ['modelValue', 'options', 'disabled'],
                    emits: ['update:modelValue'],
                    template: `<select :value="modelValue" :disabled="disabled" @change="$emit('update:modelValue', options.find((option) => String(option.value) === $event.target.value)?.value)">
                        <option v-for="option in options" :key="option.value" :value="option.value" :disabled="option.disabled">{{ option.label }}</option>
                    </select>`,
                },
                'mt-text-field': realComponents
                    ? (MtTextField as Component)
                    : {
                          props: ['modelValue', 'disabled', 'error'],
                          emits: ['update:modelValue', 'focus', 'blur'],
                          template: `<div><input :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)" @focus="$emit('focus', $event)" @blur="$emit('blur', $event)"></div>`,
                      },
            },
        },
    });
}
