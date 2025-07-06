<?php

namespace Swordfox\Shopify\Task;

ini_set('max_execution_time', '300');

use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\BuildTask;
use Exception;
use Swordfox\Shopify\Client;

/**
 * Class Import
 *
 * @author Graham McLellan
 */
class Webhooks extends BuildTask
{
    protected $title = 'View / Create shopify webhooks';

    protected $description = 'View / Create shopify webhooks';

    protected $enabled = true;

    public $api_limit;

    public function run($request)
    {
        $baseurl = Director::AbsoluteBaseURL();

        if (!$webhooks_create = Client::config()->get('webhooks_create')) {
            throw new Exception('No webhooks found.');
        }

        try {
            $client = new Client();
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            exit($e->getMessage());
        } catch (\Exception $e) {
            exit($e->getMessage());
        }

        $urlParts = explode('/', $_SERVER['REQUEST_URI']);
        $urlPartsCheckIndex = (Director::is_cli() ? 3 : 4); // Cron or Browser

        $create = false;
        $delete = false;

        if (isset($urlParts[$urlPartsCheckIndex])) {
            if ($urlParts[$urlPartsCheckIndex]=='create') {
                $create = true;
            } elseif ($urlParts[$urlPartsCheckIndex]=='delete') {
                $deleteid = $urlParts[$urlPartsCheckIndex+1];

                if ($deleteid) {
                    $delete = true;
                }
            }
        }

        if (!Director::is_cli()) {
            echo "<pre>";
        }

        if ($create) {
            foreach ($webhooks_create as $webhook => $address) {
                $data = [
                    'form_params' => [
                        "webhook" => [
                            "topic" => $webhook,
                            "address" => $baseurl.$address,
                            "format"=> "json"
                        ]
                    ]
                ];

                try {
                    $response = $client->createWebhook($data);
                } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                    //print_r($e);
                    echo($e->getMessage());
                }
            }

            echo '<h2>Webhooks Created!</h2>';
            echo '<hr />';
        } elseif ($delete) {
            try {
                $response = $client->deleteWebhook($deleteid);
                echo '<h2>Webhook Deleted!</h2>';
                echo '<hr />';
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                //print_r($e);
                echo($e->getMessage());
            }
        }

        try {
            $response = $client->getWebhooks();
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            //print_r($e);
            exit($e->getMessage());
        }

        $webhooksBody = $response->getBody()->getContents();

        if ($webhooks = json_decode($webhooksBody)) {
            echo '<h2>Webhooks</h2>';
            echo '<ul>';
            foreach ($webhooks->webhooks as $webhook) {
                echo '<li>';
                echo 'Topic: '.$webhook->topic.' (<a href="/dev/tasks/Swordfox-Shopify-Task-Webhooks/delete/'.$webhook->id.'">Delete</a>)';
                echo '<br />Address: '.$webhook->address;
                echo '<br />ID: '.$webhook->id;
                echo '<br />Format: '.$webhook->format;
                echo '</li>';
            }
            echo '</ul>';
        }

        if (!Director::is_cli()) {
            echo "</pre>";
        }

        exit('Done');
    }
}
