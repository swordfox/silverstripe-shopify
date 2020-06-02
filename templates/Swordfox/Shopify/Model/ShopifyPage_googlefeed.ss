<?xml version="1.0"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
    <channel>
        <title>{$SiteConfig.Title}</title>
        <link>{$BaseHref}</link>
        <description>Feed of products from website / shopify.</description>

        <% loop Products %>
        <item>
            <!-- The following attributes are always required -->
            <g:id>$ID</g:id>
            <g:title>$Title.XML</g:title>
            <g:description><% if Content %>$Content.Summary(100).XML<% else %>$Title.XML<% end_if %></g:description>
            <g:link>{$AbsoluteLink}</g:link>
            <g:image_link>$OriginalSrc</g:image_link>
            <g:condition>used</g:condition>
            <g:availability>in stock</g:availability>
            <% with Variants.First %>
            <% if CompareAt %>
            <g:price>$CompareAt NZD</g:price>
            <g:sale_price>$Price NZD</g:sale_price>
            <% else %>
            <g:price>$Price NZD</g:price>
            <% end_if %>
            <% end_with %>
            <g:shipping>
                <g:country>NZ</g:country>
                <g:service>Standard</g:service>
                <g:price>0 NZD</g:price>
            </g:shipping>

            <!-- 2 of the following 3 attributes are required fot this item according to the Unique Product Identifier Rules -->
            <!--<g:gtin>$ShopifyID</g:gtin>-->
            <!--<g:brand>WIW</g:brand>-->
            <g:mpn>$SKU</g:mpn>

            <!-- The following attributes are not required for this item, but supplying them is recommended -->
            <!--<g:google_product_category>$Collections.First.Title.XML</g:google_product_category>-->
            <% if $ProductType %>
            <g:product_type>$ProductType.XML</g:product_type>
            <% end_if %>
        </item>
        <% end_loop %>
    </channel>
</rss>