<div class="product-slider" data-product-slider="true">

    <div class="product-slider--container is--horizontal">
        {foreach $aprioriArticles as $article}
            <!-- Product box will be placed here. -->
        <div class="product-slider--item">



                <a href="{$article.linkDetails}"
                   title="{$article.articleName|escape}"
                   class="product--image">
                    {block name='frontend_listing_box_article_image_element'}
                        <span class="image--element">
            {block name='frontend_listing_box_article_image_media'}
                <span class="image--media">

                    {$desc = $article.articleName|escape}

                    {if isset($article.image.thumbnails)}

                        {if $article.image.description}
                            {$desc = $article.image.description|escape}
                        {/if}

                        {block name='frontend_listing_box_article_image_picture_element'}
                            <img srcset="{$article.image.thumbnails[0].sourceSet}"
                                 alt="{$desc}"
                                 title="{$desc|truncate:160}"/>
                        {/block}
                    {else}

                        <img src="{link file='frontend/_public/src/img/no-picture.jpg'}"
                             alt="{$desc}"
                             title="{$desc|truncate:160}"/>
                    {/if}
                </span>
            {/block}
        </span>
                    {/block}
                </a>




        </div>
            <!-- Product box will be placed here. -->
        {/foreach}
    </div>
</div>