{if $aprioriArticles|count > 1}
    <div class="panel has--border recommend-panel">
        <h3 class="panel--title is--underline">Wird oft zusammen gekauft</h3>
        <div class="panel--body is--wide">


            <div class="product-slider" data-product-slider="true">

                <div class="product-slider--container is--horizontal">
                    <!-- Product box will be placed here. -->

                    <!-- Passende Produkte -->
                    {assign var="sum_price" value="0"}
                    {$counter_div = 1}
                    {foreach from=$aprioriArticles item=apriori_article name=apri}
                        <div class="product-slider--item paulrecommenditem" id="item{$counter_div}">
                            <div>
                                <a href="{$apriori_article.linkDetailsRewrited}">
                                    <img class="recommend--img" src="{$apriori_article.image.thumbnails[0].source}">
                                </a>
                            </div>
                            {if !$smarty.foreach.apri.first}
                                <div class="recommended-products-plus" id="item{$counter_div}plus">
                                    <i class="icon--plus2 recommend--icon"></i>
                                </div>
                            {/if}

                            {$sum_price = $sum_price + $apriori_article.price_numeric}
                            {$counter_div = $counter_div + 1}
                        </div>
                    {/foreach}
                    <!-- Product box will be placed here. -->
                </div>
            </div>

        </div>
        <!-- Liste der passende Produkte -->
        <div class="list-recommended-products">

            <div class="buy-recommended">

                <form method="post" action="{url controller='PaulRecommend' action='addArticles'}"
                      class="buybox--form" data-eventname="submit" data-showmodal="false">

                    {$counter_checkbox = 1}
                    {foreach from=$aprioriArticles item=apriori_article}
                        <label for="checkbox{$counter_checkbox}">
                            <input class="selectproduct" id="checkbox{$counter_checkbox}" type="checkbox"
                                   name="recomendedArticles[]"
                                   value="{$apriori_article.ordernumber}"
                                   data-price={$apriori_article.price|replace:",":"."} checked/>
                            {if $sArticle.ordernumber == $apriori_article.ordernumber}
                                <strong>Dieser Artikel</strong>
                                :
                            {/if}
                            [{$apriori_article.ordernumber}] {$apriori_article.articleName} - {$apriori_article.price} €
                        </label>
                        <span class="checkbox--state"></span>
                        <br/>
                        {$counter_checkbox = $counter_checkbox + 1}
                    {/foreach}

                    <div class="sum--price--container">
                        <span class="sum--price">Gesamtpreis: <span
                                    class="gesamtpreis">{$sum_price|replace:".":","}</span>€</span>
                    </div>

                    <button class="buybox--button block btn action--to-basket is--primary is--icon-right is--center is--small"
                            name="{s name='ButtonToBasket'}In den Warenkorb{/s}">
                        {s name='ButtonToBasket'}Artikel in den Warenkorb{/s} <i class="icon--arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
{/if}

<!-- Dev -->
{*<h2>DEVELOP</h2>

<h2>Apriori</h2>
<pre>
    {print_r($aprioriArticles)}
</pre>*}
<!-- Dev -->