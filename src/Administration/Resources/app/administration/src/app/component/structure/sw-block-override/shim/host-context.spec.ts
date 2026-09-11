/** @sw-package framework
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { createShimSlot, resetShimSlotState } from './create-shim-slot';
import { indexTwigBlocksFromTemplate, resetBlockIndex } from 'src/core/factory/twig-block-index';
import createDataScopeFixture from '../sw-block-override.spec/test-utils/create-data-scope-fixture';
import '../../../../store/block-override.store';

describe('legacy Twig host context', () => {
    const wrappers: ReturnType<typeof mount>[] = [];
    beforeEach(() => {
        jest.spyOn(console, 'warn').mockImplementation(() => {});
        jest.spyOn(console, 'error').mockImplementation(() => {});
    });
    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
        resetBlockIndex();
        resetShimSlotState();
        jest.restoreAllMocks();
    });
    function slotFor(template: string) {
        return createShimSlot({ componentName: 'audit-host', innerTemplate: template, legacyConditionCases: [] }, 'audit');
    }
    async function mountBlocks(template: string, options = {}) {
        const wrapper = mount(
            { template, data: () => ({ label: 'HOST' }), ...options },
            {
                global: {
                    plugins: [createDataScopeFixture()],
                    components: {
                        'sw-block': await wrapTestComponent('sw-block', { sync: true }),
                        'sw-block-parent': await wrapTestComponent('sw-block-parent', { sync: true }),
                    },
                },
            },
        );
        wrappers.push(wrapper);
        return wrapper;
    }
    it('uses the host emit, slots, attributes and refs', async () => {
        const slot = slotFor(
            '<div><button ref="button" @click="$emit(\'save\')">emit</button><slot /><span>{{ $attrs.title || "MISSING" }}</span></div>',
        );
        const onSave = jest.fn();
        const wrapper = mount(
            defineComponent({
                render() {
                    return h('main', slot(this));
                },
            }),
            { attrs: { title: 'HOST', onSave }, slots: { default: '<b>HOST SLOT</b>' } },
        );
        wrappers.push(wrapper);
        await wrapper.get('button').trigger('click');
        expect(onSave).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('save')).toHaveLength(1);
        expect(wrapper.text()).toContain('HOST');
        expect(wrapper.text()).toContain('HOST SLOT');
        expect(wrapper.vm.$refs.button).toBe(wrapper.get('button').element);
    });
    it('resolves host local components and directives', () => {
        const slot = slotFor('<audit-local v-audit-local />');
        const mounted = jest.fn();
        const wrapper = mount(
            defineComponent({
                components: { AuditLocal: { template: '<b>LOCAL</b>' } },
                directives: { AuditLocal: { mounted } },
                render() {
                    return h('main', slot(this));
                },
            }),
        );
        wrappers.push(wrapper);
        expect(wrapper.text()).toContain('LOCAL');
        expect(mounted).toHaveBeenCalledTimes(1);
    });
    it('passes the host scope to nested overrides', async () => {
        indexTwigBlocksFromTemplate(
            'audit-host',
            '{% block audit_outer %}{% block audit_inner %}<b>nested default</b>{% endblock %}{% endblock %}',
        );
        indexTwigBlocksFromTemplate('audit-host', '{% block audit_inner %}<i>{{ label || "MISSING" }}</i>{% endblock %}');
        const wrapper = await mountBlocks('<sw-block name="audit_outer" :data="$dataScope" />');
        expect(wrapper.text()).toBe('HOST');
    });
    it('scopes block lookup to the host component', async () => {
        indexTwigBlocksFromTemplate(
            'completely-unrelated-component',
            '{% block audit_shared %}<i>WRONG COMPONENT OVERRIDE</i>{% endblock %}',
        );
        const wrapper = await mountBlocks('<sw-block name="audit_shared" :data="$dataScope"><b>BASE</b></sw-block>', {
            name: 'audit-host',
        });
        expect(wrapper.text()).toBe('BASE');
    });
    it('renders the same predecessor for each parent occurrence', async () => {
        indexTwigBlocksFromTemplate('audit-host', '{% block audit_repeat %}{% parent %}{% parent %}{% endblock %}');
        const wrapper = await mountBlocks('<sw-block name="audit_repeat" :data="$dataScope"><b>BASE</b></sw-block>');
        expect(wrapper.findAll('b')).toHaveLength(2);
    });
    it('writes root v-model to host state without replacing the input', async () => {
        const slot = slotFor('<input v-model="label" />');
        const captured = jest.fn();
        const wrapper = mount(
            defineComponent({
                data: () => ({ label: 'HOST' }),
                render() {
                    return h('main', slot(this));
                },
            }),
            { global: { config: { errorHandler: captured } } },
        );
        wrappers.push(wrapper);
        const failures: Error[] = [];
        const handleError = (event: ErrorEvent) => {
            if (event.error instanceof Error) failures.push(event.error);
            event.preventDefault();
        };
        window.addEventListener('error', handleError);
        const input = wrapper.get('input').element;
        try {
            await wrapper.get('input').setValue('NEW');
        } finally {
            window.removeEventListener('error', handleError);
        }
        expect(wrapper.vm.label).toBe('NEW');
        expect(wrapper.get('input').element).toBe(input);
        expect(failures).toEqual([]);
        expect(captured).not.toHaveBeenCalled();
    });

    it('refreshes late Twig registrations without replacing an unaffected input', async () => {
        indexTwigBlocksFromTemplate('audit-host', '{% block input %}<input v-model="label" />{% endblock %}');
        const wrapper = await mountBlocks(
            '<main><sw-block name="input" :data="$dataScope" /><sw-block name="late" :data="$dataScope"><i>before</i></sw-block></main>',
            { name: 'audit-host' },
        );
        const input = wrapper.get('input').element;
        await wrapper.get('input').setValue('edited');
        indexTwigBlocksFromTemplate('audit-host', '{% block late %}<b>after</b>{% endblock %}');
        await flushPromises();
        expect(wrapper.get('b').text()).toBe('after');
        expect(wrapper.get('input').element).toBe(input);
        expect(wrapper.get('input').element.value).toBe('edited');
    });
});
