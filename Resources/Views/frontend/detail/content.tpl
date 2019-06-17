{extends file="parent:frontend/detail/content.tpl"}

{block name="frontend_detail_index_bundle"}
    {$smarty.block.parent}
    {if !$sArticle.show_recommend_articles}
        {action module="widgets" controller="LoadArticleWidget" action="loadArticles" sArticle=$sArticle}
    {/if}
{/block}