{extends file="parent:frontend/detail/content.tpl"}

{block name="frontend_detail_index_bundle"}
    {$smarty.block.parent}
    {action module="widgets" controller="LoadArticleWidget" action="loadArticles" sArticle=$sArticle}
{/block}