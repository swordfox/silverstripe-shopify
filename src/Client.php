<?php

namespace Swordfox\Shopify;

use Exception;
use SilverStripe\Core\Config\Configurable;

/**
 * Class Client
 *
 * @package Swordfox\Shopify
 *
 * @author Graham McLellan
 */
class Client
{
    use Configurable;

    const EXCEPTION_NO_API_KEY = 0;
    const EXCEPTION_NO_API_PASSWORD = 1;
    const EXCEPTION_NO_DOMAIN = 2;

    /**
     * @config null|string
     */
    private static $api_key = null;

    /**
     * @config null|string
     */
    private static $api_password = null;

    /**
     * @config null|string
     */
    private static $storefront_access_token = null;

    /**
     * @config null|string
     */
    private static $shopify_domain = null;

    /**
     * @config null|string
     */
    private static $shared_secret = null;

    /**
     * @config null|int
     */
    public $api_limit = null;

    public $cron_interval = null;

    /**
     * @var \GuzzleHttp\Client|null
     */
    protected $client = null;

    /**
     * Get a list of available products
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function products()
    {
        return $this->client->request('GET', 'admin/api/' . $this->api_version . '/products.json?limit=' . $this->api_limit . '&updated_at_min=' . date(DATE_ATOM, strtotime('-1 day')) . '&order=updated_at+desc');
    }

    /**
     * Get information about a specific product
     *
     * @param string $productId
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function product($product_id)
    {
        return $this->client->request('GET', 'admin/api/' . $this->api_version . '/products/' . $product_id . '.json');
    }

    /**
     * Get information about a specific product
     *
     * @param string $productId
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function productmetafields($product_id)
    {
        return $this->client->request('GET', 'admin/api/' . $this->api_version . '/products/' . $product_id . '/metafields.json');
    }

    /**
     * Get a list of available locations
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function locations()
    {
        return $this->client->request('GET', 'admin/api/' . $this->api_version . '/locations.json');
    }

    public function deleteProduct($product_id)
    {
        $data = [
            'form_params' => [
                "product" => [
                    "id" => $product_id,
                    "status" => "archived"
                ]
            ]
        ];

        return $this->client->request('PUT', 'admin/api/' . $this->api_version . '/products/' . $product_id . '.json', $data);
    }

    /**
     * Get the available Collections
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function collections($type)
    {
        return $this->client->request('GET', 'admin/api/' . $this->api_version . '/' . $type . '.json?limit=' . $this->api_limit);
    }

    /**
     * Get the connections between Products and Collections
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function collects($product_id)
    {
        return $this->client->request('GET', 'admin/collects.json?order=updated_at+desc&limit=250&product_id=' . $product_id);
    }

    /**
     * Get the available Collection Products
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function paginationCall($url)
    {
        return $this->client->request('GET', $url);
    }

    /**
     * Post the new Webhook
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createWebhook($data)
    {
        return $this->client->request('POST', 'admin/api/' . $this->api_version . '/webhooks.json', $data);
    }

    /**
     * DELETE a Webhook created via API
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function deleteWebhook($deleteid)
    {
        return $this->client->request('DELETE', 'admin/api/' . $this->api_version . '/webhooks/' . $deleteid . '.json');
    }

    /**
     * GET a list of Webhooks created via API
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getWebhooks()
    {
        return $this->client->request('GET', 'admin/api/' . $this->api_version . '/webhooks.json');
    }

    /**
     * Get the configured Guzzle client
     *
     * @throws Exception
     */
    public function __construct()
    {
        if (!$key = self::config()->get('api_key')) {
            throw new Exception('No api key is set.', self::EXCEPTION_NO_API_KEY);
        }

        if (!$password = self::config()->get('api_password')) {
            throw new Exception('No api password is set.', self::EXCEPTION_NO_API_PASSWORD);
        }

        if (!$domain = self::config()->get('shopify_domain')) {
            throw new Exception('No shopify domain is set.', self::EXCEPTION_NO_DOMAIN);
        }

        if (!$this->api_limit = self::config()->get('api_limit')) {
            $this->api_limit = 50; // Default to 50 if not set
        }

        if (!$this->api_version = self::config()->get('api_version')) {
            $this->api_version = '2021-01';
        }

        if (!$this->cron_interval = self::config()->get('cron_interval')) {
            $this->cron_interval = '-12 hours'; // Default to 12 hours
        }

        $this->client = new \GuzzleHttp\Client(
            [
                'base_uri' => "https://$domain",
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Authorization' => 'Basic ' . base64_encode("$key:$password")
                ],
                'verify' => false
            ]
        );
    }
}
