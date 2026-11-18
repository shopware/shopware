/**
 * Label issues and pull requests by the area named in their body, e.g. the "Affected area /
 * extension" dropdown of the bug report template.
 *
 * Labels are only ever added, never removed, and only through `addLabels`. Replacing the whole
 * label set from a snapshot, as `srvaroa/labeler` did, dropped every label another workflow
 * added in the meantime, such as the `milestone/` label set on `opened` (#23133).
 */

export type BodyLabelRule = { label: string; pattern: RegExp };

export const BODY_LABEL_RULES: BodyLabelRule[] = [
    { label: 'Platform(Default)', pattern: /Platform\(Default\)/ },
    { label: 'extension/Commercial', pattern: /Extension:Commercial/ },
    { label: 'extension/B2B-Suite', pattern: /Extension:B2B-Suite/ },
    { label: 'extension/CMS-Extensions', pattern: /Extension:CMS-Extensions/ },
    { label: 'extension/Custom-Products', pattern: /Extension:Custom-Products/ },
    { label: 'extension/Dynamic-Access', pattern: /Extension:Dynamic-Access/ },
    { label: 'extension/Language-Pack', pattern: /Extension:Language-Pack/ },
    { label: 'extension/Migration-Assistant', pattern: /Extension:Migration-Assistant/ },
    { label: 'extension/PayPal-Integration', pattern: /Extension:PayPal-Integration/ },
    { label: 'extension/Publisher', pattern: /Extension:Publisher/ },
    { label: 'extension/Sales-Agent', pattern: /Extension:Sales-Agent/ },
    { label: 'extension/Recommendations', pattern: /Extension:Recommendations/ },
    { label: 'extension/Social-Shopping', pattern: /Extension:Social-Shopping/ },
    { label: 'extension/3D-Preview-Generator', pattern: /Extension:3D-Preview-Generator/ },
    { label: 'extension/Image-Editor', pattern: /Extension:Image-Editor/ },
    { label: 'extension/Copilot', pattern: /Extension:Copilot/ },
    { label: 'extension/Agentic-Commerce', pattern: /Extension:Agentic-Commerce/ },
    { label: 'service/shopware-payments', pattern: /Extension:Shopware-Payments/ },
];

export function labelsForBody(body: string | null | undefined, present: string[], rules = BODY_LABEL_RULES): string[] {
    if (!body) {
        return [];
    }

    return rules.filter(({ label, pattern }) => !present.includes(label) && pattern.test(body)).map(({ label }) => label);
}

type Item = { number: number; body?: string | null; labels?: { name: string }[] };

type BodyLabelContext = {
    repo: { owner: string; repo: string };
    payload: { issue?: Item; pull_request?: Item };
};

type IssuesClient = {
    rest: {
        issues: {
            addLabels(options: { owner: string; repo: string; issue_number: number; labels: string[] }): Promise<unknown>;
        };
    };
};

type Toolkit = { github: IssuesClient; core: { info(message: string): void }; context: BodyLabelContext };

export async function applyBodyLabels({ github, core, context }: Toolkit): Promise<string[]> {
    const item = context.payload.issue ?? context.payload.pull_request;
    if (!item) {
        throw new Error('The event carries neither an issue nor a pull request.');
    }

    const labels = labelsForBody(
        item.body,
        (item.labels ?? []).map(({ name }) => name),
    );

    if (labels.length === 0) {
        core.info(`#${item.number}: no body label to add`);

        return [];
    }

    await github.rest.issues.addLabels({
        owner: context.repo.owner,
        repo: context.repo.repo,
        issue_number: item.number,
        labels,
    });
    core.info(`#${item.number}: added ${labels.join(', ')}`);

    return labels;
}
