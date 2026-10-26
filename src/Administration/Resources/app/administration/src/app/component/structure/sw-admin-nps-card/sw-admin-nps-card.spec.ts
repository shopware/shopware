/**
 * @sw-package framework
 */

import { defineComponent, ref } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import 'src/app/component/structure/sw-admin-nps-card';

const mtButtonStub = {
    name: 'mt-button',
    props: [
        'variant',
        'size',
        'square',
        'disabled',
    ],
    emits: ['click'],
    template:
        '<button class="mt-button" type="button" :disabled="disabled" @click="$emit(\'click\')"><slot name="iconFront" /><slot /></button>',
};

const mtTextareaStub = {
    name: 'mt-textarea',
    props: ['modelValue', 'label'],
    emits: ['update:modelValue'],
    template:
        '<div class="mt-textarea"><label for="reason">{{ label }}</label><textarea id="reason" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
};

async function createWrapper(props: Record<string, unknown> = {}, { attachTo }: { attachTo?: HTMLElement } = {}) {
    return mount(await wrapTestComponent('sw-admin-nps-card', { sync: true }), {
        attachTo,
        props: {
            visible: true,
            ...props,
        },
        global: {
            stubs: {
                'mt-button': mtButtonStub,
                'mt-textarea': mtTextareaStub,
                'mt-icon': true,
            },
        },
    });
}

function getScoreButton(wrapper: Awaited<ReturnType<typeof createWrapper>>, score: number) {
    return wrapper.find(`.sw-admin-nps-card__score:nth-child(${score + 1})`);
}

describe('src/app/component/structure/sw-admin-nps-card', () => {
    it('should render the question and a selectable 0-10 scale', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-admin-nps-card__title').exists()).toBe(true);

        const scaleButtons = wrapper.findAll('.sw-admin-nps-card__score');
        expect(scaleButtons).toHaveLength(11);
        expect(scaleButtons.map((button) => button.text())).toEqual([
            '0',
            '1',
            '2',
            '3',
            '4',
            '5',
            '6',
            '7',
            '8',
            '9',
            '10',
        ]);

        expect(wrapper.findAll('.sw-admin-nps-card__scale-label')).toHaveLength(2);
    });

    it('should not render anything when visible is false', async () => {
        const wrapper = await createWrapper({ visible: false });

        expect(wrapper.find('.sw-admin-nps-card').exists()).toBe(false);
    });

    it('should emit update:score and highlight the selected score', async () => {
        const wrapper = await createWrapper();

        await getScoreButton(wrapper, 5).trigger('click');
        await flushPromises();

        expect(wrapper.emitted('update:score')).toEqual([[5]]);
        expect(getScoreButton(wrapper, 5).classes()).toContain('is--selected');
    });

    it.each([
        0,
        6,
        7,
    ])('should replace the scale with the reason step after submitting a score of %i', async (score) => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-admin-nps-card__scale').exists()).toBe(true);
        expect(wrapper.find('.sw-admin-nps-card__follow-up').exists()).toBe(false);

        await getScoreButton(wrapper, score).trigger('click');
        await flushPromises();
        await wrapper.find('.sw-admin-nps-card__submit').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-admin-nps-card__scale').exists()).toBe(false);
        expect(wrapper.find('.sw-admin-nps-card__follow-up').exists()).toBe(true);
        expect(wrapper.emitted('submit')).toBeUndefined();
    });

    it.each([
        8,
        10,
    ])('should submit directly and request to be hidden for a score of %i', async (score) => {
        const wrapper = await createWrapper();

        await getScoreButton(wrapper, score).trigger('click');
        await flushPromises();
        await wrapper.find('.sw-admin-nps-card__submit').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('submit')).toEqual([[{ score, reason: '' }]]);
        expect(wrapper.emitted('update:visible')).toEqual([[false]]);
        expect(wrapper.find('.sw-admin-nps-card__follow-up').exists()).toBe(false);
    });

    it('should show an error instead of submitting when no score is selected', async () => {
        const wrapper = await createWrapper();

        const submitButton = wrapper.find('.sw-admin-nps-card__submit');
        expect(submitButton.attributes('disabled')).toBeUndefined();
        expect(wrapper.find('.sw-admin-nps-card__error').exists()).toBe(false);

        await submitButton.trigger('click');
        await flushPromises();

        const error = wrapper.find('.sw-admin-nps-card__error');
        expect(error.exists()).toBe(true);
        expect(error.attributes('role')).toBe('alert');
        expect(wrapper.find('.sw-admin-nps-card__scale').attributes('aria-describedby')).toBe(error.attributes('id'));
        expect(wrapper.emitted('submit')).toBeUndefined();
        expect(wrapper.find('.sw-admin-nps-card__follow-up').exists()).toBe(false);
    });

    it('should hide the error once a score is selected', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-admin-nps-card__submit').trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-admin-nps-card__error').exists()).toBe(true);

        await getScoreButton(wrapper, 8).trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-admin-nps-card__error').exists()).toBe(false);
        expect(wrapper.find('.sw-admin-nps-card__scale').attributes('aria-describedby')).toBeUndefined();
    });

    it('should emit update:reason when the reason is typed in the reason step', async () => {
        const wrapper = await createWrapper();

        await getScoreButton(wrapper, 1).trigger('click');
        await flushPromises();
        await wrapper.find('.sw-admin-nps-card__submit').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-admin-nps-card__follow-up textarea').setValue('A follow-up reason');
        await flushPromises();

        expect(wrapper.emitted('update:reason')).toEqual([['A follow-up reason']]);
    });

    it('should emit submit with the score and reason', async () => {
        const wrapper = await createWrapper();

        await getScoreButton(wrapper, 4).trigger('click');
        await flushPromises();
        await wrapper.find('.sw-admin-nps-card__submit').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-admin-nps-card__follow-up textarea').setValue('Because it is slow');
        await flushPromises();
        await wrapper.find('.sw-admin-nps-card__submit').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('submit')).toEqual([[{ score: 4, reason: 'Because it is slow' }]]);
        expect(wrapper.emitted('update:visible')).toEqual([[false]]);
    });

    it('should emit close and request to be hidden when the close button is clicked', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-admin-nps-card__close').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('close')).toHaveLength(1);
        expect(wrapper.emitted('update:visible')).toEqual([[false]]);
    });

    it('should emit not-now with the current state and request to be hidden when "Not now" is clicked', async () => {
        const wrapper = await createWrapper();

        await getScoreButton(wrapper, 2).trigger('click');
        await flushPromises();
        await wrapper.find('.sw-admin-nps-card__not-now').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('not-now')).toEqual([[{ score: 2, reason: '' }]]);
        expect(wrapper.emitted('update:visible')).toEqual([[false]]);
        expect(wrapper.emitted('close')).toBeUndefined();
        expect(wrapper.emitted('submit')).toBeUndefined();
    });

    it('should not hide itself, as visibility is owned by the parent', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-admin-nps-card__close').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('update:visible')).toEqual([[false]]);
        expect(wrapper.find('.sw-admin-nps-card').exists()).toBe(true);
    });

    it('should start from the score step again when the parent unmounts and re-shows it', async () => {
        const host = defineComponent({
            components: { 'sw-admin-nps-card': await wrapTestComponent('sw-admin-nps-card', { sync: true }) },
            setup() {
                return { isVisible: ref(true) };
            },
            template: '<sw-admin-nps-card v-if="isVisible" v-model:visible="isVisible" />',
        });

        const wrapper = mount(host, {
            global: {
                stubs: {
                    'mt-button': mtButtonStub,
                    'mt-textarea': mtTextareaStub,
                    'mt-icon': true,
                },
            },
        });

        await wrapper.find('.sw-admin-nps-card__score:nth-child(4)').trigger('click');
        await wrapper.find('.sw-admin-nps-card__submit').trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-admin-nps-card__follow-up').exists()).toBe(true);

        await wrapper.find('.sw-admin-nps-card__not-now').trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-admin-nps-card').exists()).toBe(false);

        (wrapper.vm as unknown as { isVisible: boolean }).isVisible = true;
        await flushPromises();

        expect(wrapper.find('.sw-admin-nps-card__scale').exists()).toBe(true);
        expect(wrapper.find('.sw-admin-nps-card__follow-up').exists()).toBe(false);
        expect(wrapper.find('.sw-admin-nps-card__score.is--selected').exists()).toBe(false);
    });

    it('should name the card region by its visible title', async () => {
        const wrapper = await createWrapper();

        const card = wrapper.find('.sw-admin-nps-card');
        const title = wrapper.find('.sw-admin-nps-card__title');

        expect(card.attributes('role')).toBe('region');
        expect(title.attributes('id')).toBeTruthy();
        expect(card.attributes('aria-labelledby')).toBe(title.attributes('id'));
    });

    it('should label the optional reason field and move focus to it when the reason step opens', async () => {
        const wrapper = await createWrapper({}, { attachTo: document.body });

        await getScoreButton(wrapper, 7).trigger('click');
        await flushPromises();
        await wrapper.find('.sw-admin-nps-card__submit').trigger('click');
        await flushPromises();

        const textarea = wrapper.findComponent({ name: 'mt-textarea' });
        expect(textarea.props('label')).toBe('global.sw-admin-nps-card.reasonLabel');
        expect(document.activeElement).toBe(wrapper.find('.sw-admin-nps-card__follow-up textarea').element);

        wrapper.unmount();
    });

    it('should keep score and reason in sync with a parent using v-model', async () => {
        const host = defineComponent({
            components: { 'sw-admin-nps-card': await wrapTestComponent('sw-admin-nps-card', { sync: true }) },
            setup() {
                return { score: ref<number | null>(null), reason: ref('') };
            },
            template: '<sw-admin-nps-card :visible="true" v-model:score="score" v-model:reason="reason" />',
        });

        const wrapper = mount(host, {
            global: {
                stubs: {
                    'mt-button': mtButtonStub,
                    'mt-textarea': mtTextareaStub,
                    'mt-icon': true,
                },
            },
        });
        const hostState = wrapper.vm as unknown as { score: number | null; reason: string };

        // Child -> parent
        await wrapper.find('.sw-admin-nps-card__score:nth-child(3)').trigger('click');
        expect(hostState.score).toBe(2);

        // Parent -> child
        hostState.score = 5;
        await flushPromises();
        expect(wrapper.find('.sw-admin-nps-card__score.is--selected').text()).toBe('5');

        await wrapper.find('.sw-admin-nps-card__submit').trigger('click');
        await flushPromises();
        await wrapper.find('.sw-admin-nps-card__follow-up textarea').setValue('Too many clicks');
        expect(hostState.reason).toBe('Too many clicks');

        hostState.reason = 'Changed by parent';
        await flushPromises();
        expect((wrapper.find('.sw-admin-nps-card__follow-up textarea').element as HTMLTextAreaElement).value).toBe(
            'Changed by parent',
        );
    });

    it('should restore the selected score and reason from props', async () => {
        const wrapper = await createWrapper({ score: 3, reason: 'Already known' });

        expect(getScoreButton(wrapper, 3).classes()).toContain('is--selected');

        await wrapper.find('.sw-admin-nps-card__submit').trigger('click');
        await flushPromises();

        expect((wrapper.find('.sw-admin-nps-card__follow-up textarea').element as HTMLTextAreaElement).value).toBe(
            'Already known',
        );
    });

    it('should hide the scale labels from screen readers and describe the edge scores', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-admin-nps-card__scale-labels').attributes('aria-hidden')).toBe('true');

        const middleAriaLabel = getScoreButton(wrapper, 5).attributes('aria-label');

        expect(getScoreButton(wrapper, 0).attributes('aria-label')).not.toBe(middleAriaLabel);
        expect(getScoreButton(wrapper, 10).attributes('aria-label')).not.toBe(middleAriaLabel);
        expect(getScoreButton(wrapper, 0).attributes('aria-label')).not.toBe(getScoreButton(wrapper, 10).attributes('aria-label'));
    });
});
