{namespace name="frontend/detail/comment"}

<div class="review--entry{if $isLast} is--last{/if}{if $vote.answer} has--answer{/if}" itemprop="review" itemscope itemtype="http://schema.org/Review">

    {* Review content - Title and content *}
    {block name='frontend_detail_comment_header'}
        <div class="entry--header">
            {block name='frontend_detail_comment_header_inner'}

                {* Star rating *}
                {block name="frontend_detail_comment_star_rating"}
                    {include file="frontend/_includes/rating.tpl" points=$vote.points base=5}
                {/block}

                {* Review author *}
                {block name='frontend_detail_comment_author'}

                    {* Author label *}
                    {block name='frontend_detail_comment_author_label'}
                        <strong class="content--label">{s name="DetailCommentInfoFrom"}{/s}</strong>
                    {/block}

                    {* Author content *}
                    {block name='frontend_detail_comment_author_content'}
                        <span class="content--field" itemprop="author">{$vote.name}</span>
                    {/block}
                {/block}

                {* Review publish date *}
                {block name='frontend_detail_comment_date'}

                    {* Review publish date label *}
                    {block name='frontend_detail_comment_date_label'}
                        <strong class="content--label">{s name="DetailCommentInfoAt"}{/s}</strong>
                    {/block}

                    {* Review publish date content *}
                    {block name='frontend_detail_comment_date_content'}
                        <meta itemprop="datePublished" content="{$vote.datum|date_format:'%Y-%m-%d'}">
                        <span class="content--field">{$vote.datum|date:"DATE_MEDIUM"}</span>
                    {/block}
                {/block}
            {/block}
        </div>
    {/block}

    {* Review content - Title and content *}
    {block name='frontend_detail_comment_text'}
        <div class="entry--content">

            {* Headline *}
            {block name='frontend_detail_comment_headline'}
                <h4 class="content--title" itemprop="name">
                    {$vote.headline}
                </h4>
            {/block}

            {* Review text *}
            {block name='frontend_detail_comment_content'}
                <p class="content--box review--content" itemprop="reviewBody">
                    {$vote.comment|nl2br}
                </p>
            {/block}
        </div>
    {/block}
</div>