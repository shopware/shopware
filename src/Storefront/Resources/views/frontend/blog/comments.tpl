<div id="blog--comments-start" class="blog--comments-wrapper block">
    <div class="blog--comments panel has--border is--rounded">

    {* Detail Comment Form *}
    {block name='frontend_blog_comments_form'}
        {include file='frontend/blog/comment/form.tpl'}
    {/block}

    {* List comments *}
    {block name='frontend_blog_comments_entry'}
        {include file='frontend/blog/comment/entry.tpl'}
    {/block}

    </div>
</div>
