type WorkspaceCreator = Pick<Entity<'user'>, 'firstName' | 'lastName' | 'username'>;

/** @private @sw-package discovery */
export function getWorkspaceCreatorName(workspace: { createdBy?: WorkspaceCreator | null }): string {
    const creator = workspace.createdBy;
    return (
        [creator?.firstName?.trim(), creator?.lastName?.trim()].filter(Boolean).join(' ') || creator?.username?.trim() || ''
    );
}
