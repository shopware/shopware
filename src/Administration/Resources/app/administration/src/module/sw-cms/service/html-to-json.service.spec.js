/**
 * @sw-package discovery
 */
import './html-to-json.service';

describe('sw-cms/service/html-to-json.service.js', () => {
    let htmlToJsonService;

    beforeEach(() => {
        htmlToJsonService = Shopware.Application.getContainer('service').htmlToJsonService;
    });

    it('should be registered to the container', () => {
        expect(htmlToJsonService).toBeDefined();
    });

    describe('transform', () => {
        it('should transform simple HTML to Tiptap JSON', () => {
            const htmlInput = '<h1>Hello</h1><p>World</p>';
            const expectedJsonOutput = {
                type: 'doc',
                content: [
                    {
                        type: 'heading',
                        attrs: { level: 1 },
                        content: [{ type: 'text', text: 'Hello' }],
                    },
                    {
                        type: 'paragraph',
                        content: [{ type: 'text', text: 'World' }],
                    },
                ],
            };

            const result = htmlToJsonService.transform(htmlInput);
            expect(JSON.parse(result)).toEqual(expectedJsonOutput);
        });

        it('should transform HTML with bold text', () => {
            const htmlInput = '<p>This is <strong>bold</strong> text.</p>';
            const expectedJsonOutput = {
                type: 'doc',
                content: [
                    {
                        type: 'paragraph',
                        content: [
                            { type: 'text', text: 'This is ' },
                            { type: 'text', marks: [{ type: 'bold' }], text: 'bold' },
                            { type: 'text', text: ' text.' },
                        ],
                    },
                ],
            };

            const result = htmlToJsonService.transform(htmlInput);
            expect(JSON.parse(result)).toEqual(expectedJsonOutput);
        });

        it('should transform HTML with an unordered list', () => {
            const htmlInput = '<ul><li>Item 1</li><li>Item 2</li></ul>';
            const expectedJsonOutput = {
                type: 'doc',
                content: [
                    {
                        type: 'bulletList',
                        content: [
                            {
                                type: 'listItem',
                                content: [
                                    {
                                        type: 'paragraph',
                                        content: [{ type: 'text', text: 'Item 1' }],
                                    },
                                ],
                            },
                            {
                                type: 'listItem',
                                content: [
                                    {
                                        type: 'paragraph',
                                        content: [{ type: 'text', text: 'Item 2' }],
                                    },
                                ],
                            },
                        ],
                    },
                ],
            };

            const result = htmlToJsonService.transform(htmlInput);
            expect(JSON.parse(result)).toEqual(expectedJsonOutput);
        });
    });
});
