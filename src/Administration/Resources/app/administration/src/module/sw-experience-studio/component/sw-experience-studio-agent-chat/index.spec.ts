import agentChatComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-agent-chat', () => {
    const methods = (
        agentChatComponent as unknown as {
            methods: Record<string, (...args: unknown[]) => unknown>;
        }
    ).methods;

    it('adds an agent response and emits a returned layout', async () => {
        const $emit = jest.fn();
        const context = {
            allowEdit: true,
            canSend: true,
            prompt: 'Add a heading',
            isLoading: false,
            error: null,
            messages: [],
            layout: [],
            rootSource: 'product',
            selectedElementId: null,
            $emit,
            agentService: () => ({
                send: jest.fn().mockResolvedValue({
                    message: 'Added a heading.',
                    layout: [{ id: 'heading', component: 'core:heading' }],
                }),
            }),
        };

        await methods.onSubmit.call(context);

        expect(context.messages).toEqual([
            { role: 'user', content: 'Add a heading' },
            { role: 'assistant', content: 'Added a heading.' },
        ]);
        expect($emit).toHaveBeenCalledWith('update-layout', [{ id: 'heading', component: 'core:heading' }]);
    });
});
