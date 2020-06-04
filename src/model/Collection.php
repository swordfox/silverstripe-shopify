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
use SilverStripe\ORM\ManyManyList;
use SilverStripe\TagField\TagField;
//use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Requirements;
use SilverStripe\ORM\FieldType\DBCurrency;
use Swordfox\Shopify\Task\Import;

/**
 * Class Collection
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
 *
 * @property int ImageID
 * @method Image Image()
 *
 * @method ManyManyList Products()
 */
class Collection extends DataObject
{
    private static $table_name = 'ShopifyCollection';

    private static $db = [
        'Title' => 'Varchar',
        'NavText' => 'Varchar',
        'URLSegment' => 'Varchar',
        'ShopifyID' => 'Varchar',
        'Content' => 'HTMLText',
        'OriginalSrc' => 'Varchar',
        'SortOrder' => 'Int',
        'Active' => 'Boolean(1)'
    ];

    private static $default_sort = 'SortOrder';

    private static $data_map = [
        'id' => 'ShopifyID',
        'handle' => 'URLSegment',
        'title' => 'Title',
        'body_html' => 'Content',
        'updated_at' => 'LastEdited',
        'created_at' => 'Created',
    ];

    private static $many_many = [
        'Products' => Product::class,
    ];

    private static $many_many_extraFields = [
        'Products' => [
            'SortValue' => 'Varchar',
            'Position' => 'Int',
            'Featured' => 'Boolean'
        ],
    ];

    private static $indexes = [
        'ShopifyID' => true,
        'URLSegment' => true
    ];

    private static $summary_fields = [
        'Title',
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
            ReadonlyField::create('OriginalSrc'),
            UploadField::create('Image')->performReadonlyTransformation(),
        ]);

        $fields->addFieldsToTab('Root.Products', [
            GridField::create('Products', 'Products', $this->Products(), GridFieldConfig_RecordViewer::create())
        ]);

        $fields->removeByName(['LinkTracking', 'FileTracking']);
        return $fields;
    }

    public function Tags()
    {
        return ProductTag::get()
            ->innerJoin('ShopifyProduct_Tags', 'ShopifyProduct_Tags.ShopifyProductTagID = ShopifyProductTag.ID')
            ->innerJoin('ShopifyCollection_Products', 'ShopifyCollection_Products.ShopifyProductID = ShopifyProduct_Tags.ShopifyProductID AND ShopifyCollection_Products.ShopifyCollectionID = '.$this->ID);
    }

    public function Link($action = null)
    {
        $shopifyPage = ShopifyPage::inst();
        return Controller::join_links($shopifyPage->Link('collection'), $this->URLSegment, $action);
    }

    public function AbsoluteLink($action = null)
    {
        return Director::absoluteURL($this->Link($action));
    }

    /**
     * Creates a new Shopify Collection from the given data
     * but does not publish it
     *
     * @param $shopifyCollection
     * @return Collection
     * @throws \SilverStripe\ORM\ValidationException
     */
    public static function findOrMakeFromShopifyData($shopifyCollection)
    {
        if (!$collection = self::getByShopifyID($shopifyCollection->id)) {
            $collection = self::create();
        }

        if (!empty($shopifyCollection->image)) {
            $collection->OriginalSrc = $shopifyCollection->image->src;
        }

        $map = self::config()->get('data_map');
        Import::loop_map($map, $collection, $shopifyCollection);

        $collection->write();
        return $collection;
    }

    /**
     * @param $shopifyId
     *
     * @return Collection
     */
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
        return ($this->Active ? true : false);
    }
}
