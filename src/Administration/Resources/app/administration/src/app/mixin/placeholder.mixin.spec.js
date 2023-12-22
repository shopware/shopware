import 'src/app/mixin/placeholder.mixin';
import { shallowMount } from '@vue/test-utils';

async function createWrapper() {
    return shallowMount({
        template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
        mixins: [
            Shopware.Mixin.getByName('placeholder'),
        ],
        data() {
            return {
                name: 'sw-mock-field',
            };
        },
    }, {
        attachTo: document.body,
    });
}

describe('src/app/mixin/placeholder.mixin.ts', () => {
    let wrapper;

    beforeEach(async () => {
        Shopware.Context.api.language = {
            id: '1a2b3c4d5e6f7g8h9i',
            parentId: 'parentLanguageId',
        };

        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    [
        [
            [
                { description: 'The product description' },
                'description',
                'fallbackSnippet',
            ],
            'The product description',
        ],
        [
            [
                undefined,
                'description',
                'fallbackSnippet',
            ],
            'fallbackSnippet',
        ],
        [
            [
                {
                    id: 'myEntityId',
                    translated: {
                        description: 'The product description with translated translations',
                    },
                },
                'description',
                'fallbackSnippet',
            ],
            'The product description with translated translations',
        ],
        [
            [
                {
                    id: 'myEntityId',
                    translations: [
                        {
                            id: 'myEntityId-parentLanguageId',
                            description: 'The product description with translations',
                        },
                    ],
                },
                'description',
                'fallbackSnippet',
            ],
            'The product description with translations',
        ],
        [
            [
                {
                    id: 'myEntityId',
                },
                'description',
                'fallbackSnippet',
            ],
            'fallbackSnippet',
        ],
    ].forEach(([args, expected], index) => {
        it(`${index}: should return the correct placeholder result: "${expected}"`, () => {
            const result = wrapper.vm.placeholder(...args);

            expect(result).toBe(expected);
        });
    });
});
