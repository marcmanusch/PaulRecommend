{extends file="parent:frontend/detail/content.tpl"}

{block name="frontend_detail_index_bundle"}
    {$smarty.block.parent}
    {if $aprioriArticles|count > 1}
        {include file="frontend/detail/recommend.tpl"}
    {/if}
{/block}