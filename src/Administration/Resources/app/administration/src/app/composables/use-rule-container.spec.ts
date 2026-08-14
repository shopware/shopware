/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import { computed, nextTick, reactive } from 'vue';
import useRuleContainer, {
    type RuleConditionNode,
    type UseRuleContainerOptions,
    type UseRuleContainerReturn,
} from './use-rule-container';

const conditionDataProviderService = { getPlaceholderData: () => ({}) };

function createComposable(options: Partial<UseRuleContainerOptions> & { condition: () => RuleConditionNode }): {
    onAddPlaceholder: jest.Mock;
    composable: UseRuleContainerReturn;
} {
    const onAddPlaceholder = (options.onAddPlaceholder ?? jest.fn()) as jest.Mock;
    let composable: UseRuleContainerReturn | undefined;

    mount(
        {
            template: '<div></div>',
            setup() {
                composable = useRuleContainer({
                    level: () => 0,
                    disabled: () => false,
                    ...options,
                    onAddPlaceholder,
                });

                return {};
            },
        },
        {
            global: {
                provide: {
                    conditionDataProviderService: computed(() => conditionDataProviderService),
                    childAssociationField: computed(() => 'children'),
                    createCondition: jest.fn(),
                    insertNodeIntoTree: jest.fn(),
                    removeNodeFromTree: jest.fn(),
                },
            },
        },
    );

    return { onAddPlaceholder, composable: composable as UseRuleContainerReturn };
}

describe('src/app/composables/use-rule-container', () => {
    it('unwraps the refs sw-condition-tree provides', () => {
        const { composable } = createComposable({ condition: () => ({}) });

        expect(composable.childAssociationField.value).toBe('children');
        expect(composable.conditionDataProviderService.value).toBe(conditionDataProviderService);
    });

    it.each([
        [
            0,
            'container-condition-level__is--even',
        ],
        [
            1,
            'container-condition-level__is--odd',
        ],
        [
            2,
            'container-condition-level__is--even',
        ],
    ])('marks level %s as %s', (level, expectedClass) => {
        const { composable } = createComposable({ condition: () => ({}), level: () => level });

        expect(composable.containerRowClass.value).toEqual({ 'is--disabled': false, [expectedClass]: true });
    });

    it('reports the disabled state on the row class', () => {
        const { composable } = createComposable({ condition: () => ({}), disabled: () => true });

        expect(composable.containerRowClass.value['is--disabled']).toBe(true);
    });

    it('counts the children of the provided association field as the next position', () => {
        const { composable } = createComposable({
            condition: () => ({
                children: [
                    {},
                    {},
                ],
            }),
        });

        expect(composable.nextPosition.value).toBe(2);
    });

    it('reports position 0 for a container without children', () => {
        expect(createComposable({ condition: () => ({}) }).composable.nextPosition.value).toBe(0);
        expect(createComposable({ condition: () => ({ children: [] }) }).composable.nextPosition.value).toBe(0);
    });

    it('asks the caller for a placeholder once the last child is gone', async () => {
        const condition = reactive<RuleConditionNode>({ children: [{}] });
        const { onAddPlaceholder } = createComposable({ condition: () => condition });

        expect(onAddPlaceholder).not.toHaveBeenCalled();

        (condition.children as unknown[]).pop();
        await nextTick();

        expect(onAddPlaceholder).toHaveBeenCalledTimes(1);
    });
});
