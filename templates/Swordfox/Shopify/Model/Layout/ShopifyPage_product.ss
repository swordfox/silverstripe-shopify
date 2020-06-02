<section class="grid-container">
    <header class="grid-x grid-padding-x">
        <div class="cell">
            <h3>$Title</h3>
            $Content

            <p class="price">
                $Price.RAW
            </p>

            <div id="product-component" data-shopifyid="{$ShopifyID}" data-shopifytitle="$Title.XML" data-shopifyprice="$PriceOnly" data-shopifylink="$Link"></div>
        </div>
    </header>
</section>

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