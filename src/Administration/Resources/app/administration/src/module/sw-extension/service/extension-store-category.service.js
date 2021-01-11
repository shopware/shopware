import ApiService from 'src/core/service/api.service';

export default class ExtensionStoreCategoryService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'plugin') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'extensionStoreCategoryService';
    }

    async getStoreCategories(context) {
        const { data } = await this.httpClient.get(
            '_action/extension/store-categories',
            {
                headers: this.basicHeaders(context),
                version: 3
            }
        );

        return this._buildCategoryTree(data);
    }

    basicHeaders(context = null) {
        const headers = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`
        };

        if (context && context.languageId) {
            headers['sw-language-id'] = context.languageId;
        }

        return headers;
    }

    _buildCategoryTree(categories) {
        const categoryTree = [];

        categories.forEach((category) => {
            category.parentId = category.parent || null;
            category.parent = null;

            if (!category.children) {
                category.children = [];
            }

            if (category.parentId === null) {
                categoryTree.push(category);

                return;
            }

            const parent = categories.find((possibleParent) => {
                return possibleParent.id === category.parentId;
            }) || null;

            category.parent = parent;

            if (!parent) {
                categoryTree.push(category);
                return;
            }

            if (!parent.children) {
                parent.children = [];
            }

            parent.children.push(category);
            parent.children.sort(compareCategoryNames);
        });

        categoryTree.sort(compareCategoryNames);

        return flattenTree(categoryTree);
    }
}

function compareCategoryNames(first, second) {
    return first.details.name.localeCompare(second.details.name);
}

function flattenTree(categoryTree) {
    return categoryTree.reduce((acc, category) => {
        acc.push(category);

        if (category.children.length > 0) {
            acc.push(...flattenTree(category.children));
        }

        return acc;
    }, []);
}
