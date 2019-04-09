<div class="panel has--border recommend-panel">
    <h3 class="panel--title is--underline">Wird oft zusammen gekauft</h3>
    <div class="panel--body is--wide">

        <!-- Passende Produkte -->
        {assign var="sum_price" value="0"}
        {foreach from=$aprioriArticles item=apriori_article name=apri}
            <div class="recommended-products">
                <a href="{$apriori_article.linkDetailsRewrited}">
                    <img class="recommend--img" src="{$apriori_article.image.thumbnails[0].source}">
                </a>
            </div>

            {if !$smarty.foreach.apri.last}
                <div class="recommended-products-plus">
                    <i class="icon--plus2 recommend--icon"></i>
                </div>
            {/if}

            {$sum_price = $sum_price + $apriori_article.price_numeric}
        {/foreach}

    </div>
    <!-- Liste der passende Produkte -->
    <div class="list-recommended-products">

        {foreach from=$aprioriArticles item=apriori_article}
            <span class="checkbox recommend">
                <input type="checkbox" checked/>
                <span class="checkbox--state"></span>
            </span>
            {if $sArticle.ordernumber == $apriori_article.ordernumber}
                <strong>Dieser Artikel</strong>
                :
            {/if}
            {$apriori_article.articleName} - {$apriori_article.price}
            <br/>
        {/foreach}

        <div class="buy-recommended">
            <div class="sum--price--container">
                <span class="sum--price">Gesamtpreis: {$sum_price|replace:".":","}€</span>
            </div>

            <form method="post" action="{url controller='PaulRecommend' action='addArticles'}"
                  class="buybox--form" data-eventname="submit" data-showmodal="false">

                {foreach from=$aprioriArticles item=apriori_article}
                    <input type="hidden" name="recomendedArticles[]" value="{$apriori_article.ordernumber}">
                {/foreach}

                <button class="buybox--button block btn action--to-basket is--primary is--icon-right is--center is--small"
                        name="{s name='ButtonToBasket'}In den Warenkorb{/s}">
                    {s name='ButtonToBasket'}Artikel in den Warenkorb{/s} <i class="icon--arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</div>


<!-- Dev -->
{*<h2>Samples</h2>
<pre>
    {print_r($samples)}
</pre>

<h2>Apriori</h2>
<pre>
    {print_r($apriori)}
</pre>*}
<!-- Dev -->