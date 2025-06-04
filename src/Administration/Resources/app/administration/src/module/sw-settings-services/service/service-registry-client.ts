/**
 * @sw-package framework
 */
import type { AxiosInstance } from 'axios';

export type ServicesRevision = {
    revision: string,
    links: {
        'feedback-url': string,
        'docs-url': string,
        'tos-url': string,
    }
}

/**
 * @private
 */
export type RevisionData = {
    'latest-revision': string,
    'available-revisions': ServicesRevision[],
};

/**
 * @private
 */
export default class {


    constructor(
        private readonly httpClient: AxiosInstance,
    ) {
    }

    getCurrentRevision(locale: string | null): Promise<RevisionData> {
        const headers = locale ? { 'Accept-Language': locale} : {};

        // return this.httpClient.get<RevisionResponse>('/api/revisions', {
        //     headers: {
        //         'Accept-Language': locale,
        //     },
        // });

        return Promise.resolve({
            "latest-revision": "2025-06-25",
            "available-revisions": [
                {
                    "revision": "2025-06-25",
                    "links": {
                        "feedback-url": "https://www.shopware.com/en/foo",
                        "docs-url": "https://www.shopware.com/de/foo",
                        "tos-url": "https://www.shopware.com/de/foo",
                    },
                },
                {
                    "revision": "2025-05-25",
                    "links": {
                        "feedback-url": "https://www.shopware.com/en/foo",
                        "docs-url": "https://www.shopware.com/de/foo",
                        "tos-url": "https://www.shopware.com/de/foo",
                    },
                },
            ],
        });
    }
}