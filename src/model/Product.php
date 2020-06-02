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
use SilverStripe\ORM\HasManyList;
use SilverStripe\TagField\TagField;
use SilverStripe\ORM\FieldType\DBIndexable;
use SilverStripe\ORM\Connect\MySQLSchemaManager;
use SilverStripe\View\Requirements;
use SilverStripe\ORM\FieldType\DBCurrency;
use Swordfox\Shopify\Task\Import;

/**
 * Class Product
 *
 * @author Bram de Leeuw
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
        'DeleteOnShopify' => 'Date'
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
            ReadonlyField::create('Tags'),
            UploadField::create('Image')->performReadonlyTransformation(),
        ]);

        $fields->addFieldsToTab('Root.Variants', [
            GridField::create('Variants', 'Variants', $this->Variants(), GridFieldConfig_RecordViewer::create())
        ]);

        $fields->addFieldsToTab('Root.Images', [
            GridField::create('Images', 'Images', $this->Images(), GridFieldConfig_RecordViewer::create())
        ]);

        $fields->removeByName(['LinkTracking','FileTracking']);
        return $fields;
    }

    public function Price()
    {
        $Variant = $this->Variants()->first();

        if ($Variant->Inventory > 0) {
            return ($Variant->CompareAt ? ('<del>$'.$Variant->CompareAt.'</del> $'.$Variant->Price) : ('$'.$Variant->Price));
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
        if (!$product = self::getByShopifyID($shopifyProduct->id)) {
            $product = self::create();
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

    public function canView($member = null)
    {
        return true;
    }
}