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
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\TagField\TagField;
use SilverStripe\Security\Permission;
//use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Requirements;
use SilverStripe\ORM\FieldType\DBCurrency;
use Swordfox\Shopify\Task\Import;
use Swordfox\Shopify\Client;

/**
 * Class Collection
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
        'Active' => 'Boolean'
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
            'Position' => 'Int'
        ],
    ];

    private static $indexes = [
        'ShopifyID' => true,
        'URLSegment' => true
    ];

    private static $searchable_fields = [
        'Title' => ['title' => 'Title'],
        'ShopifyID' => ['title' => 'ShopifyID']
    ];

    private static $summary_fields = [
        'Title',
        'ShopifyID',
        'IsActive' => 'Active'
    ];

    public function IsActive()
    {
        return DBField::create_field('Varchar', ($this->Active ? 'Yes' : 'No'));
    }

    public function getProducts()
    {
        return $this->Products()->filter(['Active' => 1, 'Online' => 1]);
    }

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
        $hide_if_no_image = Client::config()->get('hide_if_no_image');
        $hide_out_of_stock = Client::config()->get('hide_out_of_stock');

        $ProductTags = ProductTag::get()
            ->innerJoin('ShopifyProduct_Tags', 'ShopifyProduct_Tags.ShopifyProductTagID = ShopifyProductTag.ID')
            ->innerJoin('ShopifyCollection_Products', 'ShopifyCollection_Products.ShopifyProductID = ShopifyProduct_Tags.ShopifyProductID AND ShopifyCollection_Products.ShopifyCollectionID = ' . $this->ID);

        if ($hide_if_no_image) {
            $ProductTags = $ProductTags->innerJoin('ShopifyProduct', 'ShopifyCollection_Products.ShopifyProductID = ShopifyProduct.ID AND ShopifyProduct.OriginalSrc IS NOT NULL');
        }

        if ($hide_out_of_stock) {
            $ProductTags = $ProductTags->innerJoin('ShopifyProduct', 'ShopifyCollection_Products.ShopifyProductID = ShopifyProduct.ID');
            $ProductTags = $ProductTags->innerJoin('ShopifyProductVariant', 'ShopifyProductVariant.ProductID = ShopifyProduct.ID AND ShopifyProductVariant.Inventory > 0');
        }

        return $ProductTags;
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
        return ((Permission::check('CMS_ACCESS_CMSMain', 'any', $member) or $this->Active) ? true : false);
    }
}
