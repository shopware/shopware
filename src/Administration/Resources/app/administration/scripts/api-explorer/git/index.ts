/**
 * @sw-package framework
 */

export { listIgnoredDeclarationFiles } from './ignored-files';
export {
    materializeRef,
    MATERIALIZED_FILES,
    type MaterializedFile,
    type MaterializedRef,
    type MaterializeOptions,
} from './materialize';
export {
    createPullRequestResolver,
    getDefaultPullRequestResolver,
    parseGitHubRemoteUrl,
    parsePullRequestInput,
    parsePullRequestRef,
    PULL_REQUEST_NAMESPACE,
    type FetchLike,
    type GitHubRepository,
    type PullRequestInput,
    type PullRequestRef,
    type PullRequestResolution,
    type PullRequestResolver,
    type PullRequestResolverOptions,
    type PullRequestTarget,
} from './pull-request';
export {
    assertSafeRef,
    compareVersionsDescending,
    findRepoRoot,
    getWorktreeStatus,
    GitRefError,
    listRefs,
    resolveRef,
    toAdminRelativePath,
    type GitRef,
    type GitRefKind,
    type ResolveRefOptions,
    type WorktreeStatus,
} from './refs';
export { GitCommandError } from './run-git';
