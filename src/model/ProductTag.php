<?php

namespace Swordfox\Shopify\Model;

use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
//use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\Core\Convert;
use Swordfox\Shopify\Task\Import;

/**
 * Class ProductVariant
 *
 * @author Graham McLellan
 * @package Swordfox\Shopify\Model
 *
 * @property string Title
 * @property string URLSegment
 *
 * @property int ProductID
 * @method Product ProductTag()
 */
class ProductTag extends DataObject
{
    private static $table_name = 'ShopifyProductTag';

    private static $db = [
        'Title' => 'Varchar',
        'URLSegment' => 'Varchar',
    ];

    private static $summary_fields = [
        'Title'
    ];

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->extend('updateProductTag', $this);
    }

    /**
     * Creates a new Shopify Variant from the given data
     *
     * @param $shopifyVariant
     * @return ProductVariant
     * @throws \SilverStripe\ORM\ValidationException
     */

    public static function findOrMakeFromShopifyData($shopifyTag)
    {
        if (!$tag = ProductTag::get()->where("Title='".Convert::raw2sql($shopifyTag)."'")->first()) {
            $tag = ProductTag::create();
        }

        $filter = URLSegmentFilter::create();
        $t = $filter->filter($shopifyTag);

        $tag->Title = $shopifyTag;
        $tag->URLSegment = $t;

        $tag->write();

        return $tag;
    }
}
