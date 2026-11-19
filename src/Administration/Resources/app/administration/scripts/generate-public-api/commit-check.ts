/**
 * @sw-package framework
 *
 * Runs the break check on commits: on one pull request, or on every commit of a range that changed the
 * Administration sources, which shows how often the check reports something.
 */
import { classifyChanges } from './break-check';
import type { Finding } from './findings';
import { createFlagContext } from './flag-states';
import { commitsChanging, readSourceTreeAt, sourcesIdAt } from './git-source-tree';
import type { GitRepository } from './git-source-tree';
import { buildModel } from './model';
import type { Model } from './model';

/**
 * @private
 *
 * What the check says about one commit compared with its first parent. `error` is set when the generator
 * could not read one of the two commits.
 */
export interface CommitResult {
    commit: string;
    subject: string;
    findings: Finding[];
    error?: string;
}

/** @private */
export interface CommitCheck {
    compare(base: string, head: string): Finding[];
    history(range: string): CommitResult[];
}

// The parent of a commit is the next commit of a history, so a few models are all a run reuses.
const KEPT_MODELS = 4;

/**
 * The version the base branch releases next. Nothing in the repository names it, so it is the minor before the
 * next major: `v6.8.0` gives `6.7`. Before a `x.0` major every lower version counts as released.
 */
function versionBefore(majorVersion: string): string {
    const [major, minor] = majorVersion.slice(1).split('.').map(Number);

    return minor > 0 ? `${major}.${minor - 1}` : `${major - 1}.${Number.MAX_SAFE_INTEGER}`;
}

/**
 * @private
 *
 * Every commit is read with the generator and the major flags of this checkout, so two models only differ
 * where the sources do. `feature.yaml` had another format in older commits. Commits with the same sources
 * share one model. The check prints no snapshot, so a template that no component uses does not stop it.
 */
export function createCommitCheck(repository: GitRepository, majorFlags: readonly string[]): CommitCheck {
    const headVersion = versionBefore(createFlagContext(majorFlags).majorVersion);
    const models = new Map<string, Model>();
    const modelAt = (commit: string): Model => {
        const id = sourcesIdAt(repository, commit);
        const known = models.get(id);

        if (known !== undefined) {
            return known;
        }

        const model = buildModel(readSourceTreeAt(repository, commit), { majorFlags });

        models.set(id, model);

        if (models.size > KEPT_MODELS) {
            models.delete([...models.keys()][0]);
        }

        return model;
    };
    const compare = (base: string, head: string): Finding[] =>
        classifyChanges(modelAt(base), modelAt(head), { headVersion });

    return {
        compare,
        history: (range) =>
            commitsChanging(repository, range).map(({ commit, subject }) => {
                try {
                    return { commit, subject, findings: compare(`${commit}^1`, commit) };
                } catch (error) {
                    return { commit, subject, findings: [], error: error instanceof Error ? error.message : String(error) };
                }
            }),
    };
}
