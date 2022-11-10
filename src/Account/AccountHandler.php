<?php

namespace Comeen\Account\Account;

use ComeenPlay\SdkPhp\Handlers\OAuthProviderHandler;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Stevenmaguire\OAuth2\Client\Provider\Salesforce;
use Illuminate\Support\Arr;
use League\OAuth2\Client\Provider\GenericProvider;

class AccountHandler extends OAuthProviderHandler
{
    public function provideRemoteMethods()
    {
        $this->addRemoteMethod('getSites', function ($parameters, $details) {
            /** @var Account $display */
            $account = Arr::get($details, 'account');

            return $this->getSites($account);
        });

        $this->addRemoteMethod('getContent', function ($parameters, $details) {
            /** @var Account $display */
            $account = Arr::get($details, 'account');

            $siteId = Arr::get($parameters, 'site');
            $type = Arr::get($parameters, 'type');

            return $this->getContent($account, $siteId, $type);
        });
    }

    public function signin($config)
    {
        $client = $this->getClient();

        // $ds_uuid = (string)Str::uuid();
        // Session::put($ds_uuid, compact('space_name', 'account_id'));

        // $client->setState($ds_uuid);

        return $client->getAuthorizationUrl();
    }

    public function callback($request, $redirectUrl = null)
    {
        $client = $this->getClient();

        $accessToken = $client->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        $data = $this->processOptions($accessToken);
        $dataStr = json_encode($data);

        return redirect()->away($redirectUrl . "&data=$dataStr");
    }

    public function testConnection($request)
    {
    }

    private function getClient($account = null)
    {
        $client = new GenericProvider([
            'clientId'                => '3MVG9JEx.BE6yifPF3ZMWY_jWM0aap.iYHw7ieqdLJ__qLQ6useFD.K.V1XwyuvCDnR9GJ2b104imhLE9xbsz',
            'clientSecret'            => '52E0BF018FC75B68B965271DE042A53610092B738F463362C68149991C23E63F',
            'redirectUri'             => Str::replace('http:', 'https:', route('api.oauth.callback')),
            'urlAuthorize'            => 'https://login.salesforce.com/services/oauth2/authorize',
            'urlAccessToken'          => 'https://login.salesforce.com/services/oauth2/token',
            'urlResourceOwnerDetails' => ''
        ]);

        // $this->refreshToken($client);

        // if (isset($account) && Arr::get($account, 'options.access_token')) {
        //     $client->setAccessToken(Arr::get($account, 'options'));

        //     if ($client->isAccessTokenExpired()) {
        //         $refresh_token = $client->getRefreshToken();

        //         $new_access = $client->fetchAccessTokenWithRefreshToken($refresh_token);
        //         Arr::set($account, 'options.access_token', $this->processOptions($new_access));

        //         $client->setAccessToken($new_access);
        //     }
        // }

        return $client;
    }

    public function refreshToken($client = null)
    {
        if (!Arr::get($this->default_config, 'access_token')) {
            return [];
        }
        if (!$client) {
            $client = $this->getClient();
        }

        try {
            $client->setAccessToken($this->default_config);
        } catch (\InvalidArgumentException $exception) {
            return [];
        }

        if ($client->isAccessTokenExpired()) {
            $refresh_token = $client->getRefreshToken();

            $new_access = $client->fetchAccessTokenWithRefreshToken($refresh_token);
            $options = $this->processOptions($new_access);

            // Google failed to provide token: auth failed
            if (!$new_access || !isset($new_access['access_token'])) {
                return;
            }

            $client->setAccessToken($new_access);
        }

        return $client->getAccessToken();
    }

    public function callSalesforceApi($account, $uri, $method = "GET")
    {
        $client = new Client([
            'base_uri' => "https://api.ec.simpplr.com/api/",
            RequestOptions::HEADERS => [
                'accept' => 'application/json',
                'Authorization' => Arr::get($account, 'options.token_type') . " " . Arr::get($account, 'options.access_token'),
                'x-user-email' => Arr::get($account, 'options.user_email'),
            ]
        ]);

        try {
            $response = $client->request($method, $uri);
            $data = json_decode($response->getBody(), true);
            if (Arr::get($data, 'statusCode') == 200) {
                $data = Arr::get($data, 'data');
            } else {
                $data = "{ \"error\": \"An error happened\" }";
            }
        } catch (\Exception $e) {
            $data = "{ \"error\": \"" . $e . "\" }";
        }

        return $data;
    }

    public function downloadImage($account, $image_uri)
    {
        $client = new Client([
            RequestOptions::HEADERS => [
                'Authorization' => Arr::get($account, 'options.token_type') . " " . Arr::get($account, 'options.access_token'),
            ]
        ]);

        try {
            $response = $client->request("GET", $image_uri);
            $body = (string)$response->getBody();
            return "data:image/png;base64,".base64_encode($body);
        } catch (\Exception $e) {
            return "";
        }
    }

    // Simpplr Methods

    public function getSites($account)
    {
        $data = $this->callSalesforceApi($account, "sites?size=20&sort-by=alphabetical");
        return Arr::get($data, 'listOfItems');
    }

    public function getContent($account, $siteId, $type)
    {
        $data = $this->callSalesforceApi($account, "contents?size=16&filter=latest&sort-by=publishedNewest&site-id=$siteId&status=published&content-sub-type=$type");
        $content = Arr::get($data, 'listOfItems');

        foreach ($content as &$item) {
            $item["authoredBy"]["img"] = $this->downloadImage($account, $item["authoredBy"]["img"]);
            $item["img"] = $this->downloadImage($account, $item["img"]);
        }

        return $content;
    }
}
