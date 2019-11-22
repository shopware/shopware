[titleEn]: <>(Admin scaffolding components)
[__RAW__]: <>(__RAW__)

<p>With the new data handling, we implemented a list of scaffolding components to prevent boiler plate code and keep data handling as simple as possible.</p>

<p>The following components are now available:</p>

<ul>
	<li>sw-entity-listing</li>
	<li>sw-entity-multi-select</li>
	<li>sw-entity-single-select</li>
	<li>sw-one-to-many-grid</li>
</ul>

<p>This components are related to different use cases in an administration module.</p>

<p><strong>sw-entity-listing</strong><br />
A decoration of the <strong>sw-data-grid </strong>which can be used as primary listing component of a module. All functions of the <strong>sw-data-grid</strong> are supported.</p>

<p>Additionally configuration for the <strong>sw-entity-listing </strong>component are:</p>

<ul>
	<li><strong>`repository [required] - Repository</strong>

	<ul>
		<li>Provide the source repository where the data can be loaded. All operations are handled by the component itself. Pagination, sorting, row editing are supported and handled out of the box.</li>
	</ul>
	</li>
	<li><strong>items [required] - SearchResult</strong>
	<ul>
		<li>The first result set must be provided in order to avoid unnecessary server request when the initial load must contain certain logics.</li>
	</ul>
	</li>
	<li><strong>detailRoute [optional| - String</strong>
	<ul>
		<li>allows to define a route for a detail page. If set the grid creates a edit action to open the detail page</li>
	</ul>
	</li>
</ul>

<pre>
import { Component } from &#39;src/core/shopware&#39;;
import Criteria from &#39;src/core/data-new/criteria.data&#39;;
import template from &#39;./sw-show-case-list.html.twig&#39;;

Component.register(&#39;sw-show-case-list&#39;, {
    template,
    inject: [&#39;repositoryFactory&#39;],
    
    data() {
        return {
            repository: null,
            products: null
        };
    },
    
    computed: {
        columns() {
            return this.getColumns();
        }
    },
    
    created() {
        this.createdComponent();
    },
    
    methods: {
        createdComponent() {
            this.repository = this.repositoryFactory
                .create(&#39;product&#39;, &#39;/product&#39;);
        
            return this.repository
                .search(new Criteria(), Shopware.Context.api)
                .then((result) =&gt; {
                    this.products = result;
                });
        },
        
        getColumns() {
            return [{
                property: &#39;name&#39;,
                dataIndex: &#39;name&#39;,
                label: this.$tc(&#39;sw-product.list.columnName&#39;),
                routerLink: &#39;sw.show.case.detail&#39;,
                inlineEdit: &#39;string&#39;,
                allowResize: true,
                primary: true
            }];
        }
    }
});

&lt;sw-page&gt;
    &lt;template slot=&quot;content&quot;&gt;
    
    &lt;sw-entity-listing v-if=&quot;products&quot;
                        :items=&quot;products&quot;
                        :repository=&quot;repository&quot;
                        :columns=&quot;columns&quot;
                        detailRoute=&quot;sw.show.case.detail&quot; /&gt;
    
    &lt;/template&gt;
&lt;/sw-page&gt;</pre>

<p><br />
<strong>sw-one-to-many-grid</strong><br />
A decoration of the sw-data-grid which can be used (As the name suggested) to display OneToMany association in a detail page. All functions of the sw-data-grid are supported.</p>

<p>Additionally configuration for the sw-one-to-many-grid component are:</p>

<ul>
	<li><strong>collection [required] - EntityCollection</strong>

	<ul>
		<li>Provide the association collection for this grid. The grid uses it to detect the entity schema and the api route where the data can be loaded or processed.</li>
	</ul>
	</li>
	<li><strong>localMode [optional] - Boolean - default false</strong>
	<ul>
		<li>If set to false, the grid creates a repository (based on the collection data) and sends all changes directly to the server.</li>
		<li>If set to true, the grid works only with the provided collection. Changes (delete, update, create) are not send to the server directly - they will be only applied to the provided collection. Changes will be saved with the parent record.</li>
	</ul>
	</li>
</ul>

<pre>
&lt;sw-one-to-many-grid slot=&quot;grid&quot;
                    :collection=&quot;product.prices&quot;
                    :localMode=&quot;record.isNew()&quot;
                    :columns=&quot;priceColumns&quot;&gt;

&lt;/sw-one-to-many-grid&gt;</pre>

<p><br />
<strong>sw-entity-single-select</strong><br />
A decoration of<strong> sw-single-select</strong>. This component is mostly used for ManyToOne association where the related entity can be linked but not be modified (like product.manufacturer, product.tax, address.country, ...). All functions of the <strong>sw-single-select </strong>are supported.</p>

<p>Additionally configuration for the <strong>sw-entity-single-select</strong> component:</p>

<ul>
	<li><strong>entity [required] - String</strong>

	<ul>
		<li>Provide the entity name like <strong>product</strong>, <strong>product_manufacturer</strong>. The component creates a repository for this entity to display the available selection.</li>
	</ul>
	</li>
	<li>&nbsp;</li>
</ul>

<pre>
&lt;sw-entity-single-select 
    label=&quot;Entity single select&quot; 
    v-model=&quot;product.manufacturerId&quot; 
    entity=&quot;product_manufacturer&quot;&gt;
&lt;/sw-entity-single-select&gt;</pre>

<p><br />
<strong>sw-entity-multi-select</strong><br />
A decoration of <strong>sw-multi-select.</strong> This component is mostly used for ManyToMany asociation where the related entity can be linked multiple times but not be modified (like product.categories, customer.tags, ...).</p>

<p>All functions of the <strong>sw-multi-select </strong>are supported.</p>

<p>Additionally configuration for the <strong>sw-entity-multi-select </strong>component:</p>

<ul>
	<li><strong>collection [required] - EntityCollection</strong>

	<ul>
		<li>Provide an entity collection of an association (Normally used for ManyToMany association). The component creates a repository based on the collection api source and entity schema. All CRUD operations are handled inside the component and can easly be overridden in case of handling the request by yourself.</li>
	</ul>
	</li>
</ul>

<pre>
&lt;sw-entity-multi-select 
    label=&quot;Entity multi select for product categories&quot; 
    :collection=&quot;product.categories&quot;&gt;
&lt;/sw-entity-multi-select&gt;</pre>

<ul>
</ul>
