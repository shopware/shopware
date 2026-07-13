import type { ContentElementNode } from '../../types/content-element.types';
import template from './sw-experience-studio-agent-chat.html.twig';
import './sw-experience-studio-agent-chat.scss';

type AgentMessage = {
    role: 'assistant' | 'user';
    content: string;
};

type AgentTurnResponse = {
    message: string;
    layout?: ContentElementNode[];
};

type ExperienceStudioAgentService = {
    send: (payload: {
        prompt: string;
        messages: AgentMessage[];
        layout: ContentElementNode[];
        rootSource: string | null;
        selectedElementId: string | null;
        elementTypes: unknown[];
        styleOptions: Record<string, unknown>;
    }) => Promise<AgentTurnResponse>;
};

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        layout: {
            type: Array,
            required: false,
            default: () => [],
        },
        rootSource: {
            type: String,
            required: false,
            default: null,
        },
        selectedElementId: {
            type: String,
            required: false,
            default: null,
        },
        elementTypes: {
            type: Array,
            required: false,
            default: () => [],
        },
        styleOptions: {
            type: Object,
            required: false,
            default: () => ({}),
        },
        allowEdit: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: ['update-layout'],

    data(): {
        prompt: string;
        messages: AgentMessage[];
        isLoading: boolean;
        error: string | null;
    } {
        return {
            prompt: '',
            messages: [],
            isLoading: false,
            error: null,
        };
    },

    computed: {
        canSend(): boolean {
            return this.allowEdit && this.prompt.trim().length > 0 && !this.isLoading;
        },
    },

    methods: {
        agentService(): ExperienceStudioAgentService {
            return Shopware.Service('experienceStudioAgentService') as ExperienceStudioAgentService;
        },

        async onSubmit(): Promise<void> {
            if (!this.canSend) {
                return;
            }

            const prompt = this.prompt.trim();
            this.prompt = '';
            this.error = null;
            this.messages.push({ role: 'user', content: prompt });
            this.isLoading = true;

            try {
                const response = await this.agentService().send({
                    prompt,
                    messages: this.messages,
                    layout: this.layout as ContentElementNode[],
                    rootSource: this.rootSource,
                    selectedElementId: this.selectedElementId,
                    elementTypes: this.elementTypes,
                    styleOptions: this.styleOptions,
                });

                this.messages.push({ role: 'assistant', content: response.message });

                if (response.layout) {
                    this.$emit('update-layout', response.layout);
                }
            } catch {
                this.error = this.$t('sw-experience-studio.detail.agent.error');
            } finally {
                this.isLoading = false;
            }
        },
    },
});
