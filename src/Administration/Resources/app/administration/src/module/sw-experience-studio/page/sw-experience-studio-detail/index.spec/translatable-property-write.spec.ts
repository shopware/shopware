import type { ContentElementNode } from 'src/core/service/content-element.types';
import detailComponent from '../index';

const SYSTEM_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
const GERMAN_LANGUAGE_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

describe('Experience Studio detail translatable property writes', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    const elementTypeStoreFor = (translatable: boolean, propertyKey: string) => ({
        getByName: () => ({
            properties: {
                [propertyKey]: {
                    translatable,
                },
            },
        }),
    });

    const propertyWriteVm = (element: ContentElementNode, propertyKey: string, translatable: boolean) => ({
        layout: {
            layout: [element],
        },
        allowSave: true,
        selectedElementId: 'element-1' as string | null,
        showDiagnostics: false,
        editorStore: {
            pushToHistory: jest.fn(),
        },
        elementTypeStore: elementTypeStoreFor(translatable, propertyKey),
        isTranslatableProperty: methods.isTranslatableProperty,
        findElementById: () => element,
        writeElementPropertyValue: methods.writeElementPropertyValue,
        applyLayoutMutation: methods.applyLayoutMutation,
        scheduleDiagnoseLayout: jest.fn(),
        draftMutationService: jest.fn(),
        notifyMutationError: methods.notifyMutationError,
        createNotificationError: jest.fn(),
        $t: (snippet: string) => `translated:${snippet}`,
    });

    const textElement = (properties?: Record<string, unknown>): ContentElementNode => ({
        id: 'element-1',
        component: 'Sw:Content:Text',
        ...(properties === undefined ? {} : { properties }),
    });

    it.each([
        ['a string', 'Hello'],
        ['a number', 2],
        ['a boolean', false],
    ])(
        'writes %s settings value of a translatable property into the local layout as the system-language entry without a request',
        (_label, value) => {
            const vm = propertyWriteVm(textElement(), 'text', true);

            methods.onElementSettingsChange.call(vm, {
                elementId: 'element-1',
                propertyKey: 'text',
                value,
            });

            expect(vm.layout.layout[0].properties).toEqual({
                text: {
                    [SYSTEM_LANGUAGE_ID]: value,
                },
            });
            expect(vm.draftMutationService).not.toHaveBeenCalled();
        },
    );

    it('keeps the other language entries of a translatable property on a settings change', () => {
        const vm = propertyWriteVm(
            textElement({
                text: {
                    [SYSTEM_LANGUAGE_ID]: 'Hello',
                    [GERMAN_LANGUAGE_ID]: 'Hallo',
                },
            }),
            'text',
            true,
        );

        methods.onElementSettingsChange.call(vm, {
            elementId: 'element-1',
            propertyKey: 'text',
            value: 'Hello again',
        });

        expect(vm.layout.layout[0].properties).toEqual({
            text: {
                [SYSTEM_LANGUAGE_ID]: 'Hello again',
                [GERMAN_LANGUAGE_ID]: 'Hallo',
            },
        });
    });

    it('records a translatable settings change in the undo history', () => {
        const vm = propertyWriteVm(textElement({ text: { [SYSTEM_LANGUAGE_ID]: 'Hello' } }), 'text', true);
        const previousLayout = vm.layout.layout;

        methods.onElementSettingsChange.call(vm, {
            elementId: 'element-1',
            propertyKey: 'text',
            value: 'Hello again',
        });

        expect(vm.editorStore.pushToHistory).toHaveBeenCalledWith(previousLayout, 'element-1');
        expect(previousLayout[0].properties).toEqual({ text: { [SYSTEM_LANGUAGE_ID]: 'Hello' } });
    });

    it.each([
        ['an object', { text: 'Hello again' }],
        ['null', null],
    ])(
        'notifies the generic mutation error and keeps the layout for %s settings value of a translatable property',
        (_label, value) => {
            const vm = propertyWriteVm(textElement({ text: { [SYSTEM_LANGUAGE_ID]: 'Hello' } }), 'text', true);
            const previousLayout = vm.layout.layout;

            methods.onElementSettingsChange.call(vm, {
                elementId: 'element-1',
                propertyKey: 'text',
                value,
            });

            expect(vm.createNotificationError).toHaveBeenCalledWith({
                message: 'translated:sw-experience-studio.detail.mutationErrors.generic',
            });
            expect(vm.layout.layout).toBe(previousLayout);
            expect(vm.editorStore.pushToHistory).not.toHaveBeenCalled();
        },
    );

    it('writes a settings change of a non-translatable property into the local layout as the plain value', () => {
        const vm = propertyWriteVm(
            {
                id: 'element-1',
                component: 'Sw:Content:Headline',
                properties: {
                    headline: 'Hello',
                },
            },
            'headline',
            false,
        );

        methods.onElementSettingsChange.call(vm, {
            elementId: 'element-1',
            propertyKey: 'headline',
            value: 'Hello again',
        });

        expect(vm.layout.layout[0].properties).toEqual({ headline: 'Hello again' });
        expect(vm.draftMutationService).not.toHaveBeenCalled();
    });
});
