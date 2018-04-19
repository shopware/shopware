{function name="categories_top" level=0}

	{$columnIndex = 0}
	{$menuSizePercentage = 100 - (25 * $columnAmount * intval($hasTeaser))}
	{$columnCount = 4 - ($columnAmount * intval($hasTeaser))}

	<ul class="menu--list menu--level-{$level} columns--{$columnCount}"{if $level === 0} style="width: {$menuSizePercentage}%;"{/if}>
		{block name="frontend_advanced_menu_list"}
			{foreach $categories as $category}
				{if !$category.displayInNavigation}
					{continue}
				{/if}

				{if $category.externalLink}
					{$categoryLink = $category.externalLink}
				{elseif $category.blog}
					{$categoryLink = "{config name="baseFile"}?sViewport=blog&sCategory={$category.id}"}
				{else}
					{$categoryLink = "{config name="baseFile"}?sViewport=cat&sCategory={$category.id}"}
				{/if}

				<li class="menu--list-item item--level-{$level}"{if $level === 0} style="width: 100%"{/if}>
					{block name="frontend_advanced_menu_list_item"}
						<a href="{$categoryLink|escapeHtml}" class="menu--list-item-link" title="{$category.name|escape}">{$category.name}</a>

						{if $category.children}
							{call name=categories_top categories=$category.children level=$level+1}
						{/if}
					{/block}
				</li>
			{/foreach}
		{/block}
	</ul>
{/function}

<div class="advanced-menu" data-advanced-menu="true" data-hoverDelay="{$hoverDelay}">
	{block name="frontend_advanced_menu"}
		{foreach $advancedMenu as $mainCategory}
			{if !$mainCategory.displayInNavigation}
				{continue}
			{/if}

			{if $mainCategory.externalLink}
				{$link = $mainCategory.externalLink}
			{elseif $mainCategory.blog}
				{$link = "{config name="baseFile"}?sViewport=blog&sCategory={$mainCategory.id}"}
			{else}
				{$link = "{config name="baseFile"}?sViewport=cat&sCategory={$mainCategory.id}"}
			{/if}

			{$hasCategories = $mainCategory.children|count > 0  && $columnAmount < 4}
			{$hasTeaser = (!empty($mainCategory.media) || !empty($mainCategory.cmsHeadline) || !empty($mainCategory.cmsText)) && $columnAmount > 0}

			<div class="menu--container">
				{block name="frontend_advanced_menu_main_container"}
					<div class="button-container">
						{block name="frontend_advanced_menu_button_category"}
							<a href="{$link|escapeHtml}" class="button--category" title="{s name="toCategoryBtn" namespace="frontend/advancedmenu/index"}{/s}{$mainCategory.name|escape:'html'}">
								<i class="icon--arrow-right"></i>
								{s name="toCategoryBtn"  namespace="frontend/advancedmenu/index"}{/s}{$mainCategory.name}
							</a>
						{/block}

						{block name="frontend_advanced_menu_button_close"}
							<span class="button--close">
                                <i class="icon--cross"></i>
                            </span>
						{/block}
					</div>

					{if $hasCategories || $hasTeaser}
						<div class="content--wrapper{if $hasCategories} has--content{/if}{if $hasTeaser} has--teaser{/if}">
							{if $hasCategories}
								{block name="frontend_advanced_menu_sub_categories"}
									{call name="categories_top" categories=$mainCategory.children}
								{/block}
							{/if}

							{if $hasTeaser}
								{block name="frontend_advanced_menu_teaser"}
									{if $hasCategories}
										<div class="menu--delimiter" style="right: {$columnAmount * 25}%;"></div>
									{/if}
									<div class="menu--teaser"{if $hasCategories} style="width: {$columnAmount * 25}%;"{else} style="width: 100%;"{/if}>
										{if !empty($mainCategory.media)}
											<a href="{$link|escapeHtml}" title="{s name="toCategoryBtn" namespace="frontend/advancedmenu/index"}{/s}{$mainCategory.name|escape:'html'}" class="teaser--image" style="background-image: url({media path={$mainCategory.media.path}});"></a>
										{/if}

										{if !empty($mainCategory.cmsHeadline)}
											<div class="teaser--headline">{$mainCategory.cmsHeadline}</div>
										{/if}

										{if !empty($mainCategory.cmsText)}
											<div class="teaser--text">
												{$mainCategory.cmsText|strip_tags|truncate:250:"..."}
												<a class="teaser--text-link" href="{$link|escapeHtml}" title="{s name="learnMoreLink" namespace="frontend/advancedmenu/index"}mehr erfahren{/s}">
													{s name="learnMoreLink" namespace="frontend/advancedmenu/index"}mehr erfahren{/s}
												</a>
											</div>
										{/if}
									</div>
								{/block}
							{/if}
						</div>
					{/if}
				{/block}
			</div>
		{/foreach}
	{/block}
</div>