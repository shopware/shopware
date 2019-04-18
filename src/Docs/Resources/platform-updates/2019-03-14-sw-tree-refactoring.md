[titleEn]: <>(sw-tree refactoring)
[__RAW__]: <>(__RAW__)

<p>The <strong>sw-tree</strong> was refactored again, due to the changing of the sorting.</p>

<p>To use the tree, each item should have an <strong>afterIdProperty</strong> which should contain the id of the element which is before the current item.</p>

<p>You now do not have to deconstruct the template in your component anymore to pass your own wording and functions.</p>

<p>The tree has its own <strong>addElement</strong> and <strong>addSubElement</strong> methods which need two methods from the parent-component: <strong>createNewElement</strong>, which needs to return a new entity and <strong>getChildrenFromParent</strong>, which needs to load child-items from the passed <strong>parentId</strong>.</p>

<p>If you delete an item,<strong> delete-element</strong> will be emited and can be used in the parent.</p>

<p>To get translations you can pass the <strong>translationContext</strong> prop, which is by default <strong>sw-tree</strong>. To get your desired translations you can simply ducplicate the <strong>sw-tree </strong>translations and edit them to your needs and pass <strong>sw-yourcomponen</strong>t to the prop.</p>

<p>To link the elements you can use the <strong>onChangeRoute</strong> prop which needs to be a function and is applied to all <strong>sw-tree-items</strong></p>

<p>If you need to disable the contextmenu you can pass <strong>disableContextMenu</strong></p>

<p>A visual example can be found in the <strong>sw-category-tree</strong></p>
