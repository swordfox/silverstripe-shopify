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
 *
 * @mixin ShopifyPage
 */
class ShopifyPageController extends \PageController
{
    public $start;
    public $hide_out_of_stock;
    public $hide_if_no_image;
    public $hide_if_collection_not_active;
    public $storefront_access_token;

    private static $allowed_actions = [
        'product',
        'collection',
        'webhook_delete',
        'webhook_update',
        'googlefeed'
    ];

    private static $url_handlers = [
        'webhook/delete/$Type' => 'webhook_delete',
        'webhook/update/$Type' => 'webhook_update'
    ];

    public function init()
    {
        parent::init();

        $this->hide_out_of_stock = Client::config()->get('hide_out_of_stock');
        $this->hide_if_no_image = Client::config()->get('hide_if_no_image');
        $this->hide_if_collection_not_active = Client::config()->get('hide_if_collection_not_active');

        $this->shopify_domain = Client::config()->get('shopify_domain');
        $this->storefront_access_token = Client::config()->get('storefront_access_token');
    }

    public function index()
    {
        if (Director::is_ajax() or $this->request->getVar('Ajax')=='1') {
            return $this->customise(array('Ajax'=>1))->renderwith('Swordfox/Shopify/Includes/AllProductsInner');
        } else {
            return array();
        }
    }

    public function AllProducts($Paginated=true)
    {
        $request = $this->getRequest();
        $this->start = ($request->getVar('start') ? $request->getVar('start') : 0);

        $Products = Product::get()->filter(['Active'=>1]);

        if ($this->hide_if_collection_not_active) {
            $Products = $Products
                ->innerJoin('ShopifyCollection_Products', 'ShopifyCollection_Products.ShopifyProductID = ShopifyProduct.ID')
                ->innerJoin('ShopifyCollection', 'ShopifyCollection.ID = ShopifyCollection_Products.ShopifyCollectionID AND ShopifyCollection.Active = 1');
        }

        if ($this->hide_if_no_image) {
            $Products = $Products->where('ShopifyProduct.OriginalSrc IS NOT NULL');
        }

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
        $sort = ($request->getVar('sort') ? $request->getVar('sort') : null);

        if (!$urlSegment = $request->param('ID')) {
            $this->httpError(404);
        }

        /**
 * @var Collection $Collection
*/
        if (!$Collection = DataObject::get_one(Collection::class, ['URLSegment' => $urlSegment, 'Active' => 1])) {
            $this->httpError(404);
        }

        $this->SelectedTag = null;

        if ($tagSegment = $request->param('OtherID') and $tag = DataObject::get_one(ProductTag::class, ['URLSegment' => $tagSegment])) {
            $this->SelectedTag = $tag;

            $this->MetaTitle = $tag->Title.' - '.$Collection->Title.' - '.$this->Title;

            $Products = $Collection->Products()
                ->innerJoin('ShopifyProduct_Tags', 'ShopifyProduct.ID = ShopifyProduct_Tags.ShopifyProductID AND ShopifyProduct_Tags.ShopifyProductTagID = '.$tag->ID);
        } else {
            $this->MetaTitle = $Collection->Title.' - '.$this->Title;

            $Products = $Collection->Products();
        }

        $Products = $Products->filter(['Active'=>1]);

        if ($sort) {
            switch ($sort) {
            case 'title':
                $sortvar = 'Title';
                break;

            case 'titledesc':
                $sortvar = 'Title DESC';
                break;

            case 'created':
                $sortvar = 'Created';
                break;

            default:
                $sortvar = 'Created DESC';
                break;
            }

            $Products = $Products->sort($sortvar);
        }

        if ($this->hide_if_no_image) {
            $Products = $Products->where('ShopifyCollection.OriginalSrc IS NOT NULL');
        }

        if ($this->hide_out_of_stock) {
            $Products = $Products->innerJoin('ShopifyProductVariant', 'ShopifyProductVariant.ProductID = ShopifyProduct.ID AND Inventory > 0');
        }

        $Collection->ItemsLeft = $Products->count() - ($start + $this->owner->PageLimit);

        $Collection->ProductsPaginated = PaginatedList::create(
            $Products,
            $this->getRequest()
        )->setPageLength($this->owner->PageLimit);

        $this->extend('updateCollectionView', $Collection);

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

        /**
 * @var Product $Product
*/
        if (!$Product = DataObject::get_one(Product::class, ['URLSegment' => $urlSegment, 'Active' => 1])) {
            $this->httpError(404);
        }

        if ($this->hide_if_no_image and !$Product->OriginalSrc) {
            $this->httpError(404);
        }

        $this->productactive = true;
        $this->productselectedurl = $Product->Link();

        $this->MetaTitle = $Product->Title;

        $this->extend('updateProductView', $Product);

        if (Director::is_ajax() or $request->getVar('Ajax')=='1') {
            return $Product->customise(array('Ajax'=>1, 'MobileOrTablet'=>$this->owner->MobileOrTablet))->renderwith('Swordfox/Shopify/Includes/ProductInner');
        } else {
            return $this->render($Product);
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
        $data = '{"id":6538030776417,"title":"7 For All Mankind Cami Size 10","body_html":"\u003cp\u003eBlack silk cami by 7 For All Mankind, size M on the label, NZ 10.\u003c\/p\u003e\n\u003cp\u003e \u003c\/p\u003e","vendor":"WIW","product_type":"Cami’s and Singlets","created_at":"2021-03-01T07:06:45+13:00","handle":"7-for-all-mankind-cami-size-10","updated_at":"2021-03-30T15:25:14+13:00","published_at":null,"template_suffix":"","status":"active","published_scope":"global","tags":"Cami\'s and Singlets","admin_graphql_api_id":"gid:\/\/shopify\/Product\/6538030776417","variants":[{"id":39252435533921,"product_id":6538030776417,"title":"Default Title","price":"45.00","sku":"WIW0011736","position":1,"inventory_policy":"deny","compare_at_price":null,"fulfillment_service":"manual","inventory_management":"shopify","option1":"Default Title","option2":null,"option3":null,"created_at":"2021-03-01T07:06:45+13:00","updated_at":"2021-03-30T15:25:14+13:00","taxable":true,"barcode":"605604","grams":0,"image_id":null,"weight":0.0,"weight_unit":"kg","inventory_item_id":41346353463393,"inventory_quantity":1,"old_inventory_quantity":1,"requires_shipping":true,"admin_graphql_api_id":"gid:\/\/shopify\/ProductVariant\/39252435533921"}],"options":[{"id":8408611127393,"product_id":6538030776417,"name":"Title","position":1,"values":["Default Title"]}],"images":[{"id":27968027328609,"product_id":6538030776417,"position":1,"created_at":"2021-03-04T10:19:53+13:00","updated_at":"2021-03-04T10:19:53+13:00","alt":null,"width":1080,"height":1439,"src":"https:\/\/cdn.shopify.com\/s\/files\/1\/0279\/5156\/2849\/products\/image_05428045-eac0-4ba6-a69d-99ab513cd552.png?v=1614806393","variant_ids":[],"admin_graphql_api_id":"gid:\/\/shopify\/ProductImage\/27968027328609"},{"id":27968027459681,"product_id":6538030776417,"position":2,"created_at":"2021-03-04T10:19:55+13:00","updated_at":"2021-03-04T10:19:55+13:00","alt":null,"width":1080,"height":1439,"src":"https:\/\/cdn.shopify.com\/s\/files\/1\/0279\/5156\/2849\/products\/image_18d142b9-0bb4-42d7-b40b-ade7ac398029.png?v=1614806395","variant_ids":[],"admin_graphql_api_id":"gid:\/\/shopify\/ProductImage\/27968027459681"}],"image":{"id":27968027328609,"product_id":6538030776417,"position":1,"created_at":"2021-03-04T10:19:53+13:00","updated_at":"2021-03-04T10:19:53+13:00","alt":null,"width":1080,"height":1439,"src":"https:\/\/cdn.shopify.com\/s\/files\/1\/0279\/5156\/2849\/products\/image_05428045-eac0-4ba6-a69d-99ab513cd552.png?v=1614806393","variant_ids":[],"admin_graphql_api_id":"gid:\/\/shopify\/ProductImage\/27968027328609"}}';
        $verified = 1;
        */

        /*
        // Example Collection Data
        $data = '{"id":163499343969,"handle":"womens-medium","updated_at":"2021-03-30T15:59:01+13:00","published_at":null,"sort_order":"best-selling","template_suffix":"","published_scope":"global","title":"Women’s - Medium (Sizes 12-14)","body_html":"\u003cp\u003eAll items are in excelled pre-loved condition. We sell quality brands from all over the world at affordable second hand prices.\u003c\/p\u003e\n\u003cp\u003e \u003c\/p\u003e","admin_graphql_api_id":"gid:\/\/shopify\/Collection\/163499343969"}';
        $verified = 1;
        */

        /*
        // Example Inventory Data
        $data = '{"inventory_item_id":34269676896353,"location_id":36167909473,"available":0,"updated_at":"2020-05-26T11:35:10+12:00","admin_graphql_api_id":"gid:\/\/shopify\/InventoryLevel\/70049923169?inventory_item_id=34269676896353"}';
        $verified = 1;
        */

        if ($verified) {
            $vars = json_decode($data);
            if (property_exists($vars, 'id')) {
                if ($type == 'product') {
                    $this->importProduct($vars);
                } elseif ($type == 'collection') {
                    $this->importCollection($vars, $client=null, '-1 hour');
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
        $webhooks_shared_secret = Client::config()->get('webhooks_shared_secret');

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
