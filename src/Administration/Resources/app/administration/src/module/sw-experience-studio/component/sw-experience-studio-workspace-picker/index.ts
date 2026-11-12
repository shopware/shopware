import type { ExperienceStudioWorkspaceStore } from '../../store/experience-studio-workspace.store';
import template from './sw-experience-studio-workspace-picker.html.twig';
import './sw-experience-studio-workspace-picker.scss';

/** @private @sw-package discovery */
export default Shopware.Component.wrapComponentConfig({
    template,
    emits: [
        'select',
        'create',
        'close',
        'rename',
        'delete',
    ],
    data() {
        return { page: 1, term: '' };
    },
    computed: {
        workspaceStore(): ExperienceStudioWorkspaceStore {
            return Shopware.Store.get('experienceStudioWorkspace' as never) as ExperienceStudioWorkspaceStore;
        },
    },
    created(): void {
        void this.workspaceStore.load();
    },
    methods: {
        onSearch(term: string): void {
            this.term = term;
            this.page = 1;
            void this.workspaceStore.load(1, term);
        },
        onPageChange({ page }: { page: number }): void {
            this.page = page;
            void this.workspaceStore.load(page, this.term);
        },
    },
});
