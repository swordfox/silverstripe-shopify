<?php

namespace Swordfox\Shopify\Model;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Director;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Core\Convert;

use Swordfox\Shopify\Client;

/**
 * Class ShopifyPageController
 * @mixin ShopifyPage
 */
class ShopifyPageController extends \PageController
{
    public $start;
    public $hide_out_of_stock;
    public $storefront_access_token;

    private static $allowed_actions = [
        'product',
        'collection',
        'webhook_delete',
        'webhook_update',
        'search',
        'googlefeed'
    ];

    private static $url_handlers = [
        'webhook/delete/$Type' => 'webhook_delete',
        'webhook/update/$Type' => 'webhook_update'
    ];

    public function init()
    {
        parent::init();

        $this->hide_out_of_stock = \Swordfox\Shopify\Client::config()->get('hide_out_of_stock');
        $this->shopify_domain = \Swordfox\Shopify\Client::config()->get('shopify_domain');
        $this->storefront_access_token = \Swordfox\Shopify\Client::config()->get('storefront_access_token');
    }

    public function index()
    {
        if (Director::is_ajax() or $this->request->getVar('Ajax')=='1') {
            return $this->customise(array('Ajax'=>1))->renderwith('Swordfox/Shopify/Includes/AllProductsInner');
        } else {
            return array();
        }
    }

    public function search()
    {
        $this->searchactive = true;
        $request = $this->getRequest();
        $this->start = ($request->getVar('start') ? $request->getVar('start') : 0);

        $keywords = Convert::raw2sql(trim($request->getVar('keyword')));
        $keywordssafe = $keywords;

        if (empty($keywords) or strlen($keywords) < 3) {
            $AllProducts = ArrayList::create();

            $this->Title = 'Search';
            $this->MetaTitle = "Search";
            $this->Content = "<p>No results, please enter a search term 3 or more characters long.</p>";
        } else {
            $andProcessor = function ($matches) {
                return " +" . $matches[2] . " +" . $matches[4] . " ";
            };

            $notProcessor = function ($matches) {
                return " -" . $matches[3];
            };

            $keywords = preg_replace_callback('/()("[^()"]+")( and )("[^"()]+")()/i', $andProcessor, $keywords);
            $keywords = preg_replace_callback('/(^| )([^() ]+)( and )([^ ()]+)( |$)/i', $andProcessor, $keywords);
            $keywords = preg_replace_callback('/(^| )(not )("[^"()]+")/i', $notProcessor, $keywords);
            $keywords = preg_replace_callback('/(^| )(not )([^() ]+)( |$)/i', $notProcessor, $keywords);

            $keywords = $this->addStarsToKeywords($keywords);

            $Products = Product::get()
                ->filterAny(
                    'SearchFields:Fulltext',
                    $keywords
                );


            if ($this->hide_out_of_stock) {
                $Products = $Products->innerJoin('ShopifyProductVariant', 'ShopifyProductVariant.ProductID = ShopifyProduct.ID AND Inventory > 0');
            }

            $SearchCount = $Products->count();

            $this->ItemsLeft = $SearchCount - ($this->start + $this->PageLimit);

            $this->Title = 'Search';
            $this->MetaTitle = "Search - '".$keywordssafe."'";
            $this->Content = "<p>Your search for '<em>{$keywordssafe}</em>' returned {$SearchCount} result".($SearchCount == 1 ? '' : 's')."</p>";

            $AllProducts = PaginatedList::create(
                $Products,
                $request
            )->setPageLength($this->PageLimit);
        }

        if (Director::is_ajax() or $this->request->getVar('Ajax')=='1') {
            return $this->customise(array('AllProducts'=>$AllProducts,'Ajax'=>1, 'MobileOrTablet'=>$this->owner->MobileOrTablet, 'start'=>$this->start))->renderwith('Swordfox/Shopify/Includes/AllProductsInner');
        } else {
            return $this->customise(array('AllProducts'=>$AllProducts));
        }
    }

    public function GiftVoucher()
    {
        if ($GiftVoucher = Product::get()->filter(array('ProductType'=>'Gift Card'))->first()) {
            return $GiftVoucher;
        }
    }

    public function AllProducts($Paginated=true)
    {
        $request = $this->getRequest();
        $this->start = ($request->getVar('start') ? $request->getVar('start') : 0);

        $Products = Product::get();

        if ($this->hide_out_of_stock) {
            $Products = $Products->innerJoin('ShopifyProductVariant', 'ShopifyProductVariant.ProductID = ShopifyProduct.ID AND Inventory > 0');
        }

        $this->ItemsLeft = $Products->count() - ($this->start + $this->PageLimit);

        if ($Paginated) {
            $AllProducts = PaginatedList::create(
                $Products,
                $request
            )->setPageLength($this->PageLimit);

            return $AllProducts;
        } else {
            return $Products;
        }
    }

    public function collection(HTTPRequest $request)
    {
        $start = ($request->getVar('start') ? $request->getVar('start') : 0);

        if (!$urlSegment = $request->param('ID')) {
            $this->httpError(404);
        }

        /** @var Collection $Collection */
        if (!$Collection = DataObject::get_one(Collection::class, ['URLSegment' => $urlSegment])) {
            $this->httpError(404);
        }

        $this->SelectedTag = null;

        if ($tagSegment = $request->param('OtherID') and $tag = DataObject::get_one(ProductTag::class, ['URLSegment' => $tagSegment])) {
            $this->SelectedTag = $tag;

            $this->MetaTitle = $tag->Title.' - '.$Collection->Title.' - Online Shop';

            $Products = $Collection->Products()
                ->innerJoin('ShopifyProduct_Tags', 'ShopifyProduct.ID = ShopifyProduct_Tags.ShopifyProductID AND ShopifyProduct_Tags.ShopifyProductTagID = '.$tag->ID);
        } else {
            $this->MetaTitle = $Collection->Title.' - Online Shop';

            $Products = $Collection->Products();
        }

        if ($this->hide_out_of_stock) {
            $Products = $Products->innerJoin('ShopifyProductVariant', 'ShopifyProductVariant.ProductID = ShopifyProduct.ID AND Inventory > 0');
        }

        $Collection->ItemsLeft = $Products->count() - ($start + $this->owner->PageLimit);

        $Collection->ProductsPaginated = PaginatedList::create(
            $Products,
            $this->getRequest()
        )->setPageLength($this->owner->PageLimit);

        if (Director::is_ajax() or $this->request->getVar('Ajax')=='1') {
            return $Collection->customise(array('Ajax'=>1, 'MobileOrTablet'=>$this->owner->MobileOrTablet, 'start'=>$start, 'SelectedTag'=>$this->SelectedTag))->renderwith('Swordfox/Shopify/Includes/CollectionInner');
        } else {
            return $this->render($Collection);
        }
    }

    public function product(HTTPRequest $request)
    {
        if (!$urlSegment = $request->param('ID')) {
            $this->httpError(404);
        }

        /** @var Product $product */
        if (!$product = DataObject::get_one(Product::class, ['URLSegment' => $urlSegment])) {
            $this->httpError(404);
        }

        $this->productactive = true;
        $this->productselectedurl = $product->Link();

        $this->MetaTitle = $product->Title;

        if (Director::is_ajax() or $this->request->getVar('Ajax')=='1') {
            return $product->customise(array('Ajax'=>1, 'MobileOrTablet'=>$this->owner->MobileOrTablet))->renderwith('Swordfox/Shopify/Includes/ProductInner');
        } else {
            return $this->render($product);
        }
    }

    /**
     * Shopify webhooks
     */

    public function webhook_update(HTTPRequest $request)
    {
        if (!$type = $request->param('Type')) {
            $this->httpError(404);
        }

        $hmac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
        $data = file_get_contents('php://input');
        $verified = $this->webhook_verify($data, $hmac_header);

        /*
        // Example Product Data
        $data = '{"id":4643710664801,"title":"Test product","body_html":"Test product","vendor":"The Walk in Wardrobe","product_type":"","created_at":"2020-05-26T11:01:00+12:00","handle":"test-product","updated_at":"2020-05-26T11:01:01+12:00","published_at":null,"template_suffix":"","published_scope":"global","tags":"","admin_graphql_api_id":"gid:\/\/shopify\/Product\/4643710664801","variants":[{"id":32338683166817,"product_id":4643710664801,"title":"Default Title","price":"1111.00","sku":"","position":1,"inventory_policy":"deny","compare_at_price":null,"fulfillment_service":"manual","inventory_management":"shopify","option1":"Default Title","option2":null,"option3":null,"created_at":"2020-05-26T11:01:00+12:00","updated_at":"2020-05-26T11:01:00+12:00","taxable":true,"barcode":"","grams":0,"image_id":null,"weight":0.0,"weight_unit":"kg","inventory_item_id":34269676896353,"inventory_quantity":1,"old_inventory_quantity":1,"requires_shipping":true,"admin_graphql_api_id":"gid:\/\/shopify\/ProductVariant\/32338683166817"}],"options":[{"id":6037006811233,"product_id":4643710664801,"name":"Title","position":1,"values":["Default Title"]}],"images":[{"id":15214163165281,"product_id":4643710664801,"position":1,"created_at":"2020-05-26T11:01:01+12:00","updated_at":"2020-05-26T11:01:01+12:00","alt":null,"width":630,"height":630,"src":"https:\/\/cdn.shopify.com\/s\/files\/1\/0279\/5156\/2849\/products\/5338644_0.jpg?v=1590447661","variant_ids":[],"admin_graphql_api_id":"gid:\/\/shopify\/ProductImage\/15214163165281"}],"image":{"id":15214163165281,"product_id":4643710664801,"position":1,"created_at":"2020-05-26T11:01:01+12:00","updated_at":"2020-05-26T11:01:01+12:00","alt":null,"width":630,"height":630,"src":"https:\/\/cdn.shopify.com\/s\/files\/1\/0279\/5156\/2849\/products\/5338644_0.jpg?v=1590447661","variant_ids":[],"admin_graphql_api_id":"gid:\/\/shopify\/ProductImage\/15214163165281"}}';

        // Example Inventory Data
        $data = '{"inventory_item_id":34269676896353,"location_id":36167909473,"available":0,"updated_at":"2020-05-26T11:35:10+12:00","admin_graphql_api_id":"gid:\/\/shopify\/InventoryLevel\/70049923169?inventory_item_id=34269676896353"}';
        $verified = 1;
        */

        if ($verified) {
            $vars = json_decode($data);
            if (property_exists($vars, 'id')) {
                if ($type == 'product') {
                    $this->importProduct($vars);

                    $product_id = $vars->id;

                    try {
                        $client = new Client();
                    } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                        exit($e->getMessage());
                    } catch (\Exception $e) {
                        exit($e->getMessage());
                    }

                    $this->importCollects($client, $since_id=0, $product_id);
                } elseif ($type == 'collection') {
                    $this->importCollection($vars);
                }
            }

            if ($type == 'inventory') {
                if (property_exists($vars, 'inventory_item_id')) {
                    if ($productvariant = ProductVariant::get()->where('InventoryItemID = '.$vars->inventory_item_id)->first()) {
                        $productvariant->Inventory = $vars->available;
                        $productvariant->write();
                    }
                }
            }
        }

        $file = 'webhook_'.$type.'_update.txt';
        file_put_contents($file, $data);
    }

    public function webhook_delete(HTTPRequest $request)
    {
        if (!$type = $request->param('Type')) {
            $this->httpError(404);
        }

        $hmac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
        $data = file_get_contents('php://input');
        $verified = $this->webhook_verify($data, $hmac_header);

        if ($verified) {
            $vars = json_decode($data);
            if (property_exists($vars, 'id')) {
                $id = $vars->id;

                if ($type == 'product') {
                    $product = DataObject::get_one(Product::class, ['ShopifyID' => $id]);

                    if ($product) {
                        $status=$product->Title.' deleted.';
                        $product->delete();
                    } else {
                        $status='Not found.';
                    }
                } elseif ($type == 'collection') {
                    $Collection = DataObject::get_one(Collection::class, ['ShopifyID' => $id]);

                    if ($Collection) {
                        $status=$Collection->Title.' deleted.';
                        $Collection->delete();
                    } else {
                        $status='Not found.';
                    }
                }
            }
        } else {
            $status='Error';
        }

        $file = 'webhook_'.$type.'_delete.txt';
        file_put_contents($file, $data.$status);
    }

    public function webhook_verify($data, $hmac_header)
    {
        $webhooks_shared_secret = \Swordfox\Shopify\Client::config()->get('webhooks_shared_secret');

        $calculated_hmac = base64_encode(hash_hmac('sha256', $data, $webhooks_shared_secret, true));
        return hash_equals($hmac_header, $calculated_hmac);
    }

    public function googlefeed()
    {
        return $this->customise(
            array(
                'Products'=>$this->AllProducts($Paginated=false)
            )
        );
    }
}