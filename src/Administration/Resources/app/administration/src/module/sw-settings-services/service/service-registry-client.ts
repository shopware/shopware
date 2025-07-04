/**
 * @sw-package framework
 */

/**
 * @private
 */
export type ServicesRevision = {
    revision: string;
    links: {
        'feedback-url': string;
        'docs-url': string;
        'tos-url': string;
    };
};

/**
 * @private
 */
export type RevisionData = {
    'latest-revision': string;
    'available-revisions': ServicesRevision[];
};

/**
 * @private
 */
export default class {
    private readonly registryUrl: string;

    constructor(registryUrl: string) {
        this.registryUrl = registryUrl;
    }

    async getCurrentRevision(locale: string): Promise<RevisionData> {
        const response = await fetch(new URL('/api/service/permission-revisions', this.registryUrl), {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'Accept-Language': locale,
            },
            mode: 'cors',
        });

        const content: unknown = await response.json();

        this.assertIsRevisionResponse(content);

        return content.revisions;
    }

    private assertIsRevisionResponse(content: unknown): asserts content is { revisions: RevisionData } {
        if (typeof content !== 'object' || content === null || !('revisions' in content)) {
            throw new Error('Could not fetch Revision data from Service Registry');
        }
    }
}
