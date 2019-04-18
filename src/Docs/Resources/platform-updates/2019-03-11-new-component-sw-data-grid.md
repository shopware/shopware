[titleEn]: <>(New component sw-data-grid)
[__RAW__]: <>(__RAW__)

<p>The sw-data-grid is a new component to render tables with data. It works similar to the sw-grid component but it has some additional features like hiding columns or scrolling horizontally.</p>

<p>To prevent many data lists from breaking the sw-data-grid is introduced as a new independent component. The main lists for products, orders and customers are already using the sw-data-grid component. Other lists like languages or manufactureres will be migrated in the future.</p>

<p><strong>How to use it</strong><br />
To render a very basic data grid you need two mandatory props:</p>

<p><strong>dataSource: </strong>Result from Store getList<br />
<strong>columns:</strong> Array of columns which should be displayed</p>

<pre>
&lt;sw-data-grid
&nbsp; &nbsp; dataSource=&quot;products&quot;
&nbsp; &nbsp; columns=&quot;productColumns&quot;&gt;
&lt;sw-data-grid&gt;</pre>

<p><strong>How to configure columns</strong></p>

<pre>
methods: {
&nbsp;&nbsp; &nbsp;// Define columns
&nbsp;&nbsp; &nbsp;getProductColumns() {
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;return [{
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;property: &#39;name&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;label: &#39;Name&#39;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;}, {
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;property: &#39;price.gross&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;label: &#39;Price&#39;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;}]
&nbsp;&nbsp; &nbsp;}
}

computed: {
&nbsp;&nbsp; &nbsp;// Access columns in the template
&nbsp;&nbsp; &nbsp;productColumns() {
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;return getProductColumns();
&nbsp;&nbsp; &nbsp;}
}</pre>

<p>Theoretically, you could define your columns directly in the template but it is recommended to do this inside your JavaScript. The extra method allows plugin developers to exdend the columns.</p>

<p><strong>AVAILABLE COLUMN PROPERTIES</strong></p>

<pre>
{
&nbsp;&nbsp; &nbsp;property: &#39;name&#39;,
&nbsp;&nbsp; &nbsp;label: &#39;Name&#39;
&nbsp;&nbsp; &nbsp;dataIndex: &#39;name&#39;,
&nbsp;&nbsp; &nbsp;align: &#39;right&#39;,
&nbsp;&nbsp; &nbsp;inlineEdit: &#39;string&#39;,
&nbsp;&nbsp; &nbsp;routerLink: &#39;sw.product.detail&#39;,
&nbsp;&nbsp; &nbsp;width: &#39;auto&#39;,
&nbsp;&nbsp; &nbsp;visible: true,
&nbsp;&nbsp; &nbsp;allowResize: true,
&nbsp;&nbsp; &nbsp;primary: true,
&nbsp;&nbsp; &nbsp;rawData: false
}</pre>

<p><strong>property (string, required)</strong></p>

<p>The field/property of the entity that you want to render.</p>

<p><strong>label (string, recommended)</strong></p>

<p>The label text will be shown in the grid header and the settings panel. The grid works without the label but the header and the settings panel expect a label and will show empty content when the label is not set. The settings panel and the header should be set to hidden when using no label.</p>

<p><strong>dataIndex (string, optional)</strong></p>

<p>Define a property that should be sorted when clicking the grid header. This works similar to sw-grid. The sorting is active when dataIndex ist set. The sortable property is not needed anymore.</p>

<p><strong>align (string, optional)</strong></p>

<p>The alignment of the cell content.</p>

<p>Available options: left, right, center<br />
Default: left<br />
<strong>inlineEdit (string, optional)</strong></p>

<p>Activates the inlineEdit for the column. The sw-data-grid can display default inlineEdit fields out of the box. At the moment this is only working with very basic fields and properties which are NOT an association. However, you have the possibility to render custom inlineEdit fields in the template via slot.</p>

<p>Available options: string, boolan, number<br />
<strong>routerLink (string, optional)</strong></p>

<p>Change the cell content text to a router link to e.g. redirect to a detail page. The router link will automatically get a parameter with the id of the current grid item. If you want to have different router links you can render a custom &lt;router-link&gt; via slot.</p>

<p><strong>width (string, optional)</strong></p>

<p>The width of the column. In most cases the grid gets it&#39;s columns widths automatically based on the content. If you wan&#39;t to give a column a minimal width e.g. 400px this can be helpful.</p>

<p>Default: auto<br />
<strong>visible (boolean, optional)</strong></p>

<p>Define if a column is visible. When it is not visible initially the user could toggle the visibility when the grid settings panel is activated.</p>

<p>Default: true<br />
<strong>allowResize (boolean, optional)</strong></p>

<p>When true the column header gets a drag element and the user is able to resize the column width.</p>

<p>Default: false<br />
<strong>primary (boolean, recommended)</strong></p>

<p>When true the column can not be hidden via the grid settings panel. This is highly recommended if the settings panel is active.</p>

<p>Default: false<br />
<strong>rawData (boolean, otional)</strong></p>

<p>Experimental: Render the raw data instead of meta.viewData</p>

<p><strong>Available props</strong></p>

<p><br />
<strong>dataSource (array/object, required)</strong></p>

<p>Result from Store getList</p>

<p><strong>columns (array, required)</strong></p>

<p>Array of columns which should be displayed</p>

<p><strong>identifier (string, required)</strong></p>

<p>A unique ID is needed for saving columns in the localStorage individually for each grid. When no identifier is set the grid will not save any settings like column visibility or column order.</p>

<p><strong>showSelection (boolean, optional)</strong></p>

<p>Shows a column with selection checkboxes.</p>

<p><strong>showActions (boolean, optional)</strong></p>

<p>Shows a column with an action menu.</p>

<p><strong>showHeader (boolean, optional)</strong></p>

<p>Shows the grid header</p>

<p><strong>showSettings (boolean, optional)</strong></p>

<p>Shows a small settings panel. Inside the panel the user can control the column order and visibility.</p>

<p><strong>fullPage (boolean, optional)</strong></p>

<p>Positions the grid absolute for large lists.</p>

<p><strong>allowInlineEdit (boolean, optional)</strong></p>

<p>Defines if the grid activates the inline edit mode when the user double clicks a row.</p>

<p><strong>allowColumnEdit (boolean, optional)</strong></p>

<p>Shows a small action menu in all column headers.</p>

<p><strong>isLoading (boolean, recommended)</strong></p>

<p>The isLoading state from the listing call e.g. Store getList</p>

<p><strong>skeletonItemAmount (number, optional)</strong></p>

<p>The number of skeleton items which will be displayed when the grid is currently loading.</p>

<p><strong>Available slots</strong></p>

<ul>
	<li>actions (scoped slot width &quot;items&quot;)</li>
	<li>action-modals (scoped slot width &quot;items&quot;)</li>
	<li>pagination</li>
</ul>

<p><br />
<strong>DYNAMIC SLOTS FOR COLUMN CONTENT</strong><br />
Every column creates a dynamic slot in which you can put custom HTML. This dynamic slots are prefixed with &quot;column-&quot; followed by the property of the column you want to change.</p>

<pre>
&lt;sw-data-grid
&nbsp;&nbsp; &nbsp;:dataSource=&quot;products&quot;
&nbsp;&nbsp; &nbsp;:columns=&quot;productColumns&quot;
&nbsp;&nbsp; &nbsp;:identifier=&quot;my-grid&quot;&gt;

&nbsp;&nbsp; &nbsp;&lt;template slot=&quot;column-firstName&quot; slot-scope=&quot;{ item }&quot;&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;{{ item.salutation }} {{ item.firstName }} {{ item.lastName }}
&nbsp;&nbsp; &nbsp;&lt;/template&gt;
&lt;/sw-data-grid&gt;</pre>

<p>The dynamic slots provide the following properties via slot-scope:</p>

<p><strong>item</strong></p>

<p>The current record</p>

<p><strong>column</strong></p>

<p>The current column</p>

<p><strong>compact</strong></p>

<p>Info if the grid is currently in compact mode.</p>

<p><strong>isInlineEdit</strong></p>

<p>Is the inline edit active for the current column. This can be helpful for customized form components inside the inline edit cell.</p>
