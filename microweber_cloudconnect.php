<?php
/**
 * Microweber Cloud Connect Module v0.0.1
 * Developed by Bozhidar Slaveykov - bobi@microweber.com
 */

use GuzzleHttp\Client;
use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Module related meta data.
 */
function microweber_cloudconnect_MetaData()
{

    return array(
        'DisplayName' => 'Microweber Cloud Connect',
        'APIVersion' => '1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '1111',
        'DefaultSSLPort' => '1112',
        'ServiceSingleSignOnLabel' => 'Login to Panel as User',
    );

}

/**
 * Define product configuration options.
 */
function microweber_cloudconnect_ConfigOptions()
{

    /*return [
        "platformType" => [
            "FriendlyName" => "Platform",
            "Type" => "dropdown",
            "Options" => [
                '9739' => 'Linux',
                '907' => 'WordPress',
                '15809' => 'Windows',
            ],
            "Description" => "Select Linux if unsure, use WordPress only for WordPress sites",
            "Default" => "9739",
        ],
    ];*/
    return [];
}

/**
 * Provision a new instance of a product/service.
 *
 * @param array $params
 *
 * @return string
 */
function microweber_cloudconnect_CreateAccount(array $params)
{
    try {
        $get_server = Capsule::table('tblservers')
            ->where('id', $params['serverid'])->first();

        if ($get_server) {

            $payload = array(
                'm' => 'microweber_server',
                'function' => 'create_account',
                'platform' => $params['configoption1'],
                'domain' => $params['domain'],
                'username'=> $params['username'],
                'password'=> $params['domain'],
                'api_key'=> $get_server->accesshash
            );

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $get_server->hostname . '/index.php?' . http_build_query($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => 1
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);



            var_dump($response);
            die();
            if ($err) {
                return $err;
            } else {
                return 'success';
            }
        }

    } catch (Throwable $e) {

        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();

    }

}

/**
 * Test connection with the given server parameters. Expected response is JSON,
 * if its not an exception will be thrown, failing the test.
 *
 * @param array $params
 *
 * @return array
 */
function microweber_cloudconnect_TestConnection(array $params)
{
    $success = false;
    try {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $params['serverhostname'] . '/index.php?m=microweber_server&function=validate_api_key&api_key=' . $params['serveraccesshash'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ));

        $response = curl_exec($curl);
        $err      = curl_error($curl);

        curl_close($curl);

        if ($err) {
            $errorMsg = $err;
        }
    } catch (Throwable $e) {
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        $success  = false;
        $errorMsg = $e->getMessage();
    }

    $json = json_decode($response, true);

    if(isset($json['is_correct']) && $json['is_correct']) {
        $success = true;
    } else {
        $errorMsg = 'Invalid api key.';
        $success = false;
    }

    return array(
        'success' => $success,
        'error' => $errorMsg,
    );

}

/**
 * Perform single sign-on for a given instance of a product/service.
 * Called when single sign-on is requested for an instance of a product/service.
 * When successful, returns a URL to which the user should be redirected.
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 */
function microweber_cloudconnect_ServiceSingleSignOn(array $params)
{

    try {

        $microweberId = microweber_id($params);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://microweber.com/api/v1/whmcs/sso",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(['identifier' => $params['domain']]),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: Bearer " . $params['serverpassword']
            ),
        ));

        $response = curl_exec($curl);

        $err  = curl_error($curl);
        $info = curl_getinfo($curl);

        curl_close($curl);

        if ($err) {

            return array(
                'success' => false,
                'errorMsg' => $err,
            );

        } else {

            if ($info['http_code'] !== 200) {

                return array(
                    'success' => false,
                    'errorMsg' => $response,
                );

            }

            return array(
                'success' => true,
                'redirectTo' => $response,
            );

        }

    } catch (Throwable $e) {

        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return array(
            'success' => false,
            'errorMsg' => $e->getMessage(),
        );

    }

}

/**
 * Suspend a customer.
 *
 * @param array $params
 *
 * @return string
 */
function microweber_cloudconnect_SuspendAccount(array $params)
{

    try {

        $microweberId = microweber_id($params);

        $payload = [
            'status' => 'disabled',
        ];

        $microweberClient = microweber_cloudconnect_Client($params['serverpassword']);
        $microweberClient->put('sub_users/' . $microweberId, ['json' => $payload])->getBody()->getContents();

        return 'success';

    } catch (Throwable $e) {

        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

}

/**
 * Un-suspend a customer.
 *
 * @param array $params
 *
 * @return string
 */
function microweber_cloudconnect_UnsuspendAccount(array $params)
{

    try {

        $microweberId = microweber_id($params);

        $payload = [
            'status' => 'active',
        ];

        $microweberClient = microweber_cloudconnect_Client($params['serverpassword']);
        $microweberClient->put('sub_users/' . $microweberId, ['json' => $payload])->getBody()->getContents();

        return 'success';

    } catch (Throwable $e) {

        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

}

/**
 * @param $apiToken
 *
 * @return Client
 */
function microweber_cloudconnect_Client($apiToken)
{

    /**
     * Setup a Guzzle Client just for this Auth request
     */
    return new Client([
        'base_url' => ['https://microweber.com/api/v1/', ['version' => 'v1']],
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken
        ]
    ]);

}
