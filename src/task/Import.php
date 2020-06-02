<?php

namespace Swordfox\Shopify\Task;

ini_set('max_execution_time', '300');

use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\BuildTask;

use Swordfox\Shopify\Client;

use SilverStripe\Dev\Debug;
use SilverStripe\Dev\Backtrace;

/**
 * Class Import
 *
 * @author Bram de Leeuw
 */
class Import extends BuildTask
{
    protected $title = 'Import shopify products';

    protected $description = 'Import shopify products from the configured store';

    protected $enabled = true;

    public function run($request)
    {
        $productsonly = false;

        $urlParts = explode('/', $_SERVER['REQUEST_URI']);

        // Cron
        if (isset($urlParts[3]) and $urlParts[3]=='productsonly') {
            $productsonly = true;
        }

        // Browser
        if (isset($urlParts[4]) and $urlParts[4]=='productsonly') {
            $productsonly = true;
        }

        if (!Director::is_cli()) {
            echo "<pre>";
        }

        try {
            $client = new Client();
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            exit($e->getMessage());
        } catch (\Exception $e) {
            exit($e->getMessage());
        }

        if ($productsonly) {
            $this->importProducts($client);
        } else {
            $this->importCollections($client, 'custom_collections');
            $this->importCollections($client, 'smart_collections');
            $this->importProducts($client);
            $this->importCollects($client);
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
