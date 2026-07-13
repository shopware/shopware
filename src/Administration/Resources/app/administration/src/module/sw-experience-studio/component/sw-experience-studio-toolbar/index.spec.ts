import toolbarComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-toolbar', () => {
    const methods = (
        toolbarComponent as unknown as {
            methods: Record<string, (...args: unknown[]) => unknown>;
        }
    ).methods;

    it('emits an event to open the agent chat', () => {
        const $emit = jest.fn();

        methods.onOpenAgentChat.call({ $emit });

        expect($emit).toHaveBeenCalledWith('open-agent-chat');
    });
});
