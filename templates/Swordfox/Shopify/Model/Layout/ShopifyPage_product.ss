<div class="content-container unit size3of4 lastUnit">
    <article>
        <h1>$Title</h1>
        <section class="content unit size1of2">
            $Content

            <p class="price">
                $Price.RAW
            </p>

            <div id="product-component" data-shopifyid="{$ShopifyID}" data-shopifytitle="$Title.XML" data-shopifyprice="$PriceOnly" data-shopifylink="$Link"></div>
        </section>

        <section class="content unit size1of2 lastUnit">
            <% if Images.Count %>

            <% with Images.Sort(Sort).First %>
            <div class="content unit size1of1 lastUnit">
                <a href="https://images.weserv.nl/?w=1000&amp;url={$Top.URLEncode($OriginalSrc)}">
                    <img src="https://images.weserv.nl/?w=500&amp;url={$Top.URLEncode($OriginalSrc)}" alt="" width="500" />
                </a>
            </div>
            <% end_with %>

            <% if Images.Count > 1 %>
            <div class="center content unit size1of1 lastUnit">
                <% loop Images.Sort(Sort) %>
                <div class="unit size1of4<% if not Modulus(4) %> lastUnit<% end_if %>">
                    <a href="https://images.weserv.nl/?w=500&amp;url={$Top.URLEncode($OriginalSrc)}">
                        <img src="https://images.weserv.nl/?w=85&amp;h=85&amp;fit=cover&amp;url={$Top.URLEncode($OriginalSrc)}" alt="$Title">
                    </a>
                </div>
                <% end_loop %>
            </div>
            <% end_if %>
            <% end_if %>
        </section>
    </article>
</div>

<script type="text/javascript">
    /*<![CDATA[*/
    (function() {
        var scriptURL = 'https://sdks.shopifycdn.com/buy-button/latest/buy-button-storefront.min.js';
        if (window.ShopifyBuy) {
            if (window.ShopifyBuy.UI) {
                ShopifyBuyInit();
            } else {
                loadScript();
            }
        } else {
            loadScript();
        }

        function loadScript() {
            var script = document.createElement('script');
            script.async = true;
            script.src = scriptURL;
            (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(script);
            script.onload = ShopifyBuyInit;
        }

        function ShopifyBuyInit() {
            var client = ShopifyBuy.buildClient({
                domain: '{$shopify_domain}',
                storefrontAccessToken: '{$storefront_access_token}',
            });
            ShopifyBuy.UI.onReady(client).then(function(ui) {
                ui.createComponent('product', {
                    id: document.getElementById('product-component').getAttribute('data-shopifyid'),
                    node: document.getElementById('product-component'),
                    moneyFormat: '%24%7B%7Bamount%7D%7D',
                    options: {
                        "product": {
                            "contents": {
                                "img": false,
                                "button": false,
                                "buttonWithQuantity": true,
                                "title": false,
                                "price": false
                            }
                        }
                    },
                });
            });
        }
    })();
    /*]]>*/
</script>