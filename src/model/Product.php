<?php

namespace Swordfox\Shopify\Model;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\HasManyList;
use SilverStripe\TagField\TagField;
use SilverStripe\ORM\FieldType\DBIndexable;
use SilverStripe\ORM\Connect\MySQLSchemaManager;
use SilverStripe\View\Requirements;
use SilverStripe\ORM\FieldType\DBCurrency;
use Swordfox\Shopify\Task\Import;
use Swordfox\Shopify\Client;

/**
 * Class Product
 *
 * @author Graham McLellan
 * @package Swordfox\Shopify
 * @subpackage Model
 *
 * @mixin Versioned
 *
 * @property string Title
 * @property string URLSegment
 * @property string ShopifyID
 * @property string Content
 * @property string Vendor
 * @property string ProductType
 * @property string Tags
 *
 * @property int ImageID
 * @method Image Image()
 *
 * @method HasManyList Variants()
 * @method HasManyList Images()
 */
class Product extends DataObject
{
    private static $table_name = 'ShopifyProduct';

    private static $currency = 'NZD';

    private static $default_sort = 'Created DESC';

    private static $options = [
        'product' => [
            'contents' => [
                'title' => false,
                'variantTitle' => false,
                'price' => false,
                'description' => false,
                'quantity' => false,
                'img' => false,
            ]
        ]
    ];

    private static $create_table_options = [
        MySQLSchemaManager::ID => 'ENGINE=MyISAM',
    ];

    private static $db = [
        'Title' => 'Varchar',
        'URLSegment' => 'Varchar',
        'ShopifyID' => 'Varchar',
        'Content' => 'HTMLText',
        'Vendor' => 'Varchar',
        'ProductType' => 'Varchar',
        'Tags' => 'Varchar',
        'OriginalSrc' => 'Varchar',
        'DeleteOnShopify' => 'Date',
        'ImageAdded' => 'DBDatetime'
    ];

    private static $data_map = [
        'id' => 'ShopifyID',
        'title' => 'Title',
        'body_html' => 'Content',
        'vendor' => 'Vendor',
        'product_type' => 'ProductType',
        'created_at' => 'Created',
        'handle' => 'URLSegment',
        'updated_at' => 'LastEdited',
        'tags' => 'Tags',
    ];

    private static $has_many = [
        'Variants' => ProductVariant::class,
        'Images' => ShopifyImage::class
    ];

    private static $cascade_deletes = [
        'Variants',
        'Images'
    ];

    private static $many_many = [
        'Tags' => ProductTag::class
    ];

    private static $belongs_many_many = [
        'Collections' => Collection::class
    ];

    private static $owns = [
        'Variants',
        'Images'
    ];

    private static $indexes = [
        'ShopifyID' => true,
        'URLSegment' => true,
        'SearchFields' => [
            'type' => DBIndexable::TYPE_FULLTEXT,
            'columns' => ['Title', 'Content', 'Tags'],
        ]
    ];

    private static $summary_fields = [
        'Title',
        'Vendor',
        'ProductType',
        'ShopifyID'
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab('Root.Main', [
            ReadonlyField::create('Title'),
            ReadonlyField::create('URLSegment'),
            ReadonlyField::create('ShopifyID'),
            ReadonlyField::create('Content'),
            ReadonlyField::create('Vendor'),
            ReadonlyField::create('ProductType'),
            ReadonlyField::create('OriginalSrc', 'Main Image'),
            ReadonlyField::create('DeleteOnShopify')
        ]);

        $fields->addFieldsToTab('Root.Variants', [
            GridField::create('Variants', 'Variants', $this->Variants(), GridFieldConfig_RecordViewer::create())
        ]);

        $fields->addFieldsToTab('Root.Images', [
            GridField::create('Images', 'Images', $this->Images(), GridFieldConfig_RecordViewer::create())
        ]);

        $fields->addFieldsToTab('Root.Tags', [
            GridField::create('Tags', 'Tags', $this->Tags(), GridFieldConfig_RecordViewer::create())
        ]);

        $fields->removeByName(['LinkTracking','FileTracking']);
        return $fields;
    }

    public function Price($decimals=0)
    {
        $Variant = $this->Variants()->first();

        if ($Variant->Inventory > 0) {
            return '$'.number_format($Variant->Price, $decimals).($Variant->CompareAt ? (' <del>$'.number_format($Variant->CompareAt, $decimals).'</del>') : '');
        } elseif ($this->ProductType == 'Gift Card') {
            return '';
        } else {
            return 'Sold';
        }
    }

    public function PriceOnly()
    {
        $Variant = $this->Variants()->first();

        return number_format($Variant->Price, 2);
    }

    public function OnSale()
    {
        $Variant = $this->Variants()->first();

        return ($Variant->CompareAt > $Variant->Price ? true : false);
    }

    public function New()
    {
        $new_based_on = Client::config()->get('new_based_on');
        $new_timeframe = Client::config()->get('new_timeframe');

        $new_based_on = ($new_based_on ? $new_based_on : 'Created');
        $new_timeframe = ($new_timeframe ? $new_timeframe : '+7 days');

        if($this->$new_based_on and strtotime($this->$new_based_on.' '.$new_timeframe) > time()){
            return true;
        }

        return false;
    }

    public function Link($action = null)
    {
        $shopifyPage = ShopifyPage::inst();
        return Controller::join_links($shopifyPage->Link('product'), $this->URLSegment, $action);
    }

    public function AbsoluteLink($action = null)
    {
        return Director::absoluteURL($this->Link($action));
    }

    /**
     * Creates a new Shopify Product from the given data
     * but does not publish it
     *
     * @param $shopifyProduct
     * @return Product
     * @throws \SilverStripe\ORM\ValidationException
     */
    public static function findOrMakeFromShopifyData($shopifyProduct)
    {
        $delete_on_shopify = Client::config()->get('delete_on_shopify');
        $delete_on_shopify_after = Client::config()->get('delete_on_shopify_after');

        if (!$product = self::getByShopifyID($shopifyProduct->id)) {
            $product = self::create();
        }

        // Create the images
        if (!empty($shopifyProduct->images)) {
            // Set the ImageAdded date/time if not set
            if(!$product->ImageAdded){
                $now = DBDatetime::now()->Rfc2822();
                $product->ImageAdded = $now;
            }

            $product->OriginalSrc = $shopifyProduct->images[0]->src;
        } else {
            $product->OriginalSrc = '';
        }

        $currentimages = $product->Images();
        $allimages = [];

        if (!empty($shopifyProduct->images)) {
            foreach ($shopifyProduct->images as $shopifyImage) {
                array_push($allimages, $shopifyImage->src);

                if (!$ExistingImage = ShopifyImage::get()->where("OriginalSrc='{$shopifyImage->src}'")->first()) {
                    if ($image = $product->importObject(ShopifyImage::class, $shopifyImage)) {
                        $product->Images()->add($image);
                    }
                }
            }
        }

        // Remove any images that have been deleted.
        foreach ($currentimages as $currentimage) {
            if (!in_array($currentimage->OriginalSrc, $allimages)) {
                $product->Images()->remove($currentimage);
            }
        }

        // Create the variants
        $currentvariants = $product->Variants();
        $allvariants = [];

        if (!empty($shopifyProduct->variants)) {
            foreach ($shopifyProduct->variants as $shopifyVariant) {
                // Delete if inventory_quantity = 0 and after 3 days based on updated_at 'I hope'
                if (count($shopifyProduct->variants)==1 and $delete_on_shopify) {
                    if ($shopifyVariant->inventory_quantity == 0 and $shopifyVariant->inventory_management == 'shopify' and ($product->DeleteOnShopify == '0000-00-00' or $product->DeleteOnShopify == '')) {
                        $product->DeleteOnShopify = date('Y-m-d', strtotime($delete_on_shopify_after));
                    } elseif ($shopifyVariant->inventory_quantity > 0) {
                        $product->DeleteOnShopify = '0000-00-00';
                    }
                }

                array_push($allvariants, $shopifyVariant->id);

                if ($variant = $product->importObject(ProductVariant::class, $shopifyVariant)) {
                    $product->Variants()->add($variant);
                }
            }
        }

        // Remove any variants that have been deleted.
        foreach ($currentvariants as $currentvariant) {
            if (!in_array($currentvariant->ShopifyID, $allvariants)) {
                $product->Variants()->remove($currentvariant);
            }
        }

        // Create the tags
        $currenttags = $product->Tags();
        $alltags = [];

        if (!empty($shopifyProduct->tags)) {
            $shopifyTags = array_map('trim', explode(',', $shopifyProduct->tags));

            foreach ($shopifyTags as $shopifyTag) {
                array_push($alltags, $shopifyTag);

                if ($tag = $product->importObject(ProductTag::class, $shopifyTag)) {
                    $product->Tags()->add($tag);
                }
            }
        }

        // Remove any tags that have been deleted.
        foreach ($currenttags as $currenttag) {
            if (!in_array($currenttag->Title, $alltags)) {
                $product->Tags()->remove($currenttag);
            }
        }

        $map = self::config()->get('data_map');
        Import::loop_map($map, $product, $shopifyProduct);

        $product->write();
        return $product;
    }

    public static function getByShopifyID($shopifyId)
    {
        return DataObject::get_one(self::class, ['ShopifyID' => $shopifyId]);
    }

    public static function getByURLSegment($urlSegment)
    {
        return DataObject::get_one(self::class, ['URLSegment' => $urlSegment]);
    }

    public function URLEncode($url='')
    {
        return urlencode($url);
    }

    public function canView($member = null)
    {
        $this->hide_if_no_image = Client::config()->get('hide_if_no_image');

        return ((!$this->OriginalSrc and $this->hide_if_no_image) ? false : true);
    }
}
