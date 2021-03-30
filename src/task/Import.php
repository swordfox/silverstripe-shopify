<?php

namespace Swordfox\Shopify\Task;

ini_set('max_execution_time', '300');

use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\BuildTask;

use Swordfox\Shopify\Client;

/**
 * Class Import
 *
 * @author Graham McLellan
 */
class Import extends BuildTask
{
    protected $title = 'Import shopify products';

    protected $description = 'Import shopify products from the configured store';

    protected $enabled = true;

    public $api_limit;
    public $cron_interval;

    public function run($request)
    {
        try {
            $client = new Client();
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            exit($e->getMessage());
        } catch (\Exception $e) {
            exit($e->getMessage());
        }

        $productsonly = false;
        $productsall = false;
        $collectionsonly = false;

        $urlParts = explode('/', $_SERVER['REQUEST_URI']);
        $urlPartsCheckIndex = (Director::is_cli() ? 3 : 4); // Cron or Browser

        if (isset($urlParts[$urlPartsCheckIndex])) {
            if ($urlParts[$urlPartsCheckIndex]=='productsonly') {
                $productsonly = true;
            } elseif ($urlParts[$urlPartsCheckIndex]=='productsall') {
                $productsall = true;
            } elseif ($urlParts[$urlPartsCheckIndex]=='collectionsonly') {
                $collectionsonly = true;
            }
        }

        if (!Director::is_cli()) {
            echo "<pre>";
        }

        if ($productsonly) {
            $this->importProducts($client);
        } else if ($collectionsonly) {
            $this->importCollections($client, 'custom_collections');
            $this->importCollections($client, 'smart_collections');
        } else if ($productsall) {
            $this->importProductsAll($client);
        } else {
            $this->importCollections($client, 'smart_collections', $client->cron_interval);
            $this->importProducts($client);
        }

        if (!Director::is_cli()) {
            echo "</pre>";
        }
        exit('Done');
    }

    /**
     * Loop the given data map and possible sub maps
     *
     * @param array $map
     * @param $object
     * @param $data
     */
    public static function loop_map($map, &$object, $data)
    {
        foreach ($map as $from => $to) {
            if (is_array($to) && is_object($data->{$from})) {
                self::loop_map($to, $object, $data->{$from});
            } elseif (isset($data->{$from}) && $value = $data->{$from}) {
                $object->{$to} = $value;
            }
        }
    }
}
