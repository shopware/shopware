import type { ExperienceStudioWorkspaceStore } from '../../store/experience-studio-workspace.store';
import template from './sw-experience-studio-workspace-picker.html.twig';
import './sw-experience-studio-workspace-picker.scss';

/** @private @sw-package discovery */
export default Shopware.Component.wrapComponentConfig({
    template,
    inject: ['acl'],
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
        allowCreate(): boolean {
            return this.acl.can('experience_studio.editor');
        },
        allowEdit(): boolean {
            return this.acl.can('experience_studio.editor');
        },
        allowDelete(): boolean {
            return this.acl.can('experience_studio.deleter');
        },
        workspaceStore(): ExperienceStudioWorkspaceStore {
            return Shopware.Store.get('experienceStudioWorkspace' as never) as ExperienceStudioWorkspaceStore;
        },
    },
    created(): void {
        void this.workspaceStore.load();
    },
    methods: {
        onModalRootChange(isOpen: boolean): void {
            if (!isOpen) {
                this.$emit('close');
            }
        },
        onSearch(term: string): void {
            this.term = term;
            this.page = 1;
            void this.workspaceStore.load(1, term);
        },
        onPageChange(page: number): void {
            this.page = page;
            void this.workspaceStore.load(page, this.term);
        },
    },
});
