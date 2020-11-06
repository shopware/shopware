[titleEn]: <>(HowTos)
[hash]: <>(category_how-to)
[__RAW__]: <>(__RAW__)

<style type='text/css'>

    /* Disable default elements */

    .wiki--header, .category--title, .is--xl, .category--articles {
        display: none;
    }

    .wiki--content {
        margin: 0; padding: 0;
    }

    .wiki-content--category {
        max-width: 100%;
    }

    .how-to-main-content .header-ct {
        background-color: #189eff;
        color: #fff;

        height: 230px;
        padding: 1rem;
    }

    .how-to-main-content .header-ct .headline {
        font-size: 2.5rem;
        text-align: center;
        margin: 2rem 0;
        font-weight: 600;
    }

    .how-to-main-content .ais-search-box {
        width: 100% !important;
    }

    .how-to-main-content .header--search {
        padding: 0;
        margin: 0 auto;
        width: 40%;
        display: block;
    }

    .how-to-main-content .ais-hits:not(.ais-hits__empty) {
        min-width: 320px;
        color: #607182;
        display: grid;
        grid-template-areas: 'col col col';
        grid-gap: 1.25rem;
        margin: 50px auto 50px;
        padding: 1rem;
        max-width: 1100px;
        grid-auto-rows: 250px;
        grid-template-columns: 1fr 1fr 1fr;
    }

    .how-to-main-content .ais-hits__empty {
        padding: 20px;
    }

    .how-to-main-content .ais-hits .ais-hits--item {
        border: 2px solid #dee2e5;
        border-radius: 5px;
        padding: 1.2rem;
        overflow: hidden;
        position: relative;
        min-height: 250px;
    }

    .how-to-main-content .ais-hits .ais-hits--item .title {
        min-height: 50px;
        color: #142432;
        font-size: 1.1875rem;
        margin-bottom: 1rem;
    }

    .how-to-main-content .ais-hits .ais-hits--item .title a {
        color: #142432;
    }

    .how-to-main-content .ais-hits .ais-hits--item .title a:hover {
        color: #75b9e7;
    }

    .how-to-main-content .ais-hits .ais-hits--item .short-desc {
        font-weight: 300;
    }

    @media screen and (max-width: 900px) {
        .how-to-main-content .header--search {
            width: 60%
        }

        .how-to-main-content .ais-hits:not(.ais-hits__empty) {
            margin: 2rem auto 2rem;
            grid-template-areas: 'col col';
            grid-template-columns: 1fr 1fr;
        }
    }

    @media screen and (max-width: 580px) {
        .how-to-main-content .ais-hits:not(.ais-hits__empty) {
            margin: 1rem auto 1rem;
            grid-template-areas: 'col';
            grid-auto-rows: auto;
            grid-template-columns: 1fr;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/instantsearch.js@2.10.4"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        let hitTemplate = '<div class="article"><div class="title"><a href="/{{{localization}}}{{{seoUrl}}}">{{{navigationTitle}}}</a></div><div class="short-desc">{{{metaDescription}}}</div></div>';

        var search = instantsearch({
            appId: 'NW0OL237LC',
            apiKey: '39f8cc2c26ab96068b16eaa39f95f121',
            indexName: 'WikiEntry',
            routing: true,
            searchParameters: {
                hitsPerPage: 10000,
                distinct: true,
                facets: [
                    'localization',
                    'searchableInAllLanguages',
                    'product',
                    'categories'
                ],
                facetFilters: [
                     [ "localization:en" ],
                     [ "categories:Shopware 6 - Developer > HowTos" ]
                ]
            },
        });

        search.addWidget(
            instantsearch.widgets.hits({
                container: '#how-to-hits',
                templates: {
                    item: hitTemplate,
                    empty: "We didn't find any results for the search <em>\"{{query}}\"</em>"
                }
            })
        );

        search.addWidget(
            instantsearch.widgets.searchBox({
                container: '#how-to-search-box',
                placeholder: 'Search for HowTos or keywords'
            })
        );

        search.start();
    });
</script>

<div class="how-to-main-content">
    <div class="header-ct">
        <div class="headline">
            HowTos
        </div>
        <div class="header--search">
            <div class="algolia-search-box">
                <div id="how-to-search-box">

                </div>
            </div>
        </div>
    </div>
    <div id="how-to-hits" class="article-list">
    </div>
</div>
