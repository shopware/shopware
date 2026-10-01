import type { ContentElementNode } from 'src/core/service/content-element.types';
import { AccessibilityFixError, requestAccessibilityFix } from './accessibility-fix.service';
import type { AccessibilityViolation } from './accessibility.types';

describe('module/sw-experience-studio/util/accessibility-fix.service', () => {
    const element = {
        id: 'text',
        component: 'text',
        properties: { text: 'Original' },
    } as ContentElementNode;
    const violations: AccessibilityViolation[] = [
        { id: 'color-contrast', impact: 'serious', tags: [], description: '', help: '', helpUrl: '', nodes: [] },
    ];
    const specification = { properties: { ariaLabel: { type: 'string' } } } as never;

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('sends the component and specification to the configured model', async () => {
        const fetchMock = jest.spyOn(global, 'fetch').mockResolvedValue({
            ok: true,
            json: async () => ({
                choices: [
                    {
                        message: {
                            content:
                                '{"summary":"Added label","operations":[{"type":"set-property","elementId":"text","path":"properties.ariaLabel","value":"Accessible text"}]}',
                        },
                    },
                ],
            }),
        } as Response);

        await expect(requestAccessibilityFix(element, violations, specification)).resolves.toEqual({
            summary: 'Added label',
            operations: [{ type: 'set-property', elementId: 'text', path: 'ariaLabel', value: 'Accessible text' }],
        });

        const request = JSON.parse(fetchMock.mock.calls[0]?.[1]?.body as string);
        expect(request.model).toBe('gpt-5.6-luna');
        expect(request.messages[1].content).toContain('"id":"text"');
        expect(request.messages[1].content).toContain('"elementSpecification"');
    });

    it('rejects operations for undeclared properties', async () => {
        jest.spyOn(global, 'fetch').mockResolvedValue({
            ok: true,
            json: async () => ({
                choices: [
                    {
                        message: {
                            content:
                                '{"summary":"Unsafe","operations":[{"type":"set-property","elementId":"text","path":"unknown","value":"x"}]}',
                        },
                    },
                ],
            }),
        } as Response);

        await expect(requestAccessibilityFix(element, violations, specification)).rejects.toBeInstanceOf(
            AccessibilityFixError,
        );
    });

    it('rejects failed and malformed model responses', async () => {
        jest.spyOn(global, 'fetch').mockResolvedValue({ ok: false, status: 502 } as Response);
        await expect(requestAccessibilityFix(element, violations, null)).rejects.toThrow('HTTP 502');

        jest.spyOn(global, 'fetch').mockResolvedValue({
            ok: true,
            json: async () => ({ choices: [{ message: { content: 'not json' } }] }),
        } as Response);
        await expect(requestAccessibilityFix(element, violations, null)).rejects.toThrow('invalid JSON');
    });
});
