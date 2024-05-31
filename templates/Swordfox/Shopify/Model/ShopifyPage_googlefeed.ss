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
            <g:condition>$Top.Condition</g:condition>
            <g:availability>in stock</g:availability>
            <% with Variants.First %>
            <% if CompareAt %>
            <g:price>$CompareAt $Top.Currency</g:price>
            <g:sale_price>$Price $Top.Currency</g:sale_price>
            <% else %>
            <g:price>$Price $Top.Currency</g:price>
            <% end_if %>
            <% if Weight && WeightUnit %>
                <g:shipping_weight>$Weight $WeightUnit</g:shipping_weight>
            <% end_if %>
            <% loop $ShippingZonesFromWeight %>
                <% loop $Me.ShippingCountriesNew %>       
                    <% if $ShippingRates.count > 0 %>
                        <% loop $ShippingRates %>
                        <g:shipping>
                            <g:country>$Up.Code</g:country>
                            <g:service>$Name</g:service>
                            <g:price>$Price $Top.Currency</g:price>
                        </g:shipping>
                        <% end_loop %>
                    <% end_if %>
                <% end_loop %>
            <% end_loop %>            
            <!-- 2 of the following 3 attributes are required fot this item according to the Unique Product Identifier Rules -->
            <% if $Top.GTIN %>
            <g:gtin>$Barcode</g:gtin>
            <% end_if %>
            <% if $Top.MPN %>
            <g:mpn>$SKU</g:mpn>
            <% else %>
            <g:mpn></g:mpn>
            <% end_if %>
            <% end_with %>
            <% if Brand %>
            <g:brand>$Brand</g:brand>
            <% end_if %>
            <!-- The following attributes are not required for this item, but supplying them is recommended -->
            <!--<g:google_product_category>$Collections.First.Title.XML</g:google_product_category>-->
            <% if $ProductType %>
            <g:product_type>$ProductType.XML</g:product_type>
            <% end_if %>
        </item>
        <% end_loop %>
    </channel>
</rss>