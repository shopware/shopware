import 'src/app/mixin/validation.mixin';
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount({
        template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
        mixins: [
            Shopware.Mixin.getByName('validation'),
        ],
        data() {
            return {
                value: undefined,
            };
        },
    }, {
        global: {
            provide: {
                validationService: {
                    ruleOne: () => true,
                    ruleTwo: () => true,
                    ruleThree: () => true,
                },
            },
        },
        attachTo: document.body,
    });
}

describe('src/app/mixin/validation.mixin.ts', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    [
        [true, undefined, true],
        [false, undefined, false],
        [[true, true, true], undefined, true],
        [[true, true, false], undefined, false],
        ['ruleOne,ruleTwo,ruleThree', undefined, true],
        ['ruleOne,ruleTwo,ruleFour', undefined, false],
        ['ruleOne', undefined, true],
        ['ruleFour', undefined, false],
    ].forEach(([validation, value, expected]) => {
        it(`should validate correctly. Input: "${validation}" Value: "${value}" Expect: "${expected}"`, async () => {
            await wrapper.setProps({
                validation: validation,
            });
            wrapper.vm.value = value;

            expect(wrapper.vm.isValid).toBe(expected);
        });
    });
});
