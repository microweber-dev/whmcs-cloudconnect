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
 * Get or create the custom microweber_id.
 *
 * @param array $params
 *
 * @return string
 */
function microweber_id(array $params)
{

    /**
     * Attempt to find the custom client field
     */
    $microweber_field = Capsule::table('tblcustomfields')
        ->where('fieldname', 'microweber_id')
        ->first();

    /**
     * If it cannot be found, return friendly error
     */
    if (!$microweber_field->id) {

        return 'Failed to create microweber_id, have you added your custom client field?';

    }

    /**
     * If found, check if it has a value set for this Customer
     */
    $microweber_id = Capsule::table('tblcustomfieldsvalues')
        ->where('fieldid', $microweber_field->id)
        ->where('relid', $params['clientsdetails']['userid'])
        ->first()->value;

    /**
     * If its got a value, return it
     */
    if (!empty($microweber_id)) {

        return $microweber_id;

        /**
         * If not set try and populate the value
         */
    } else {

        try {

            $payload = [
                'name' => $params['clientsdetails']['firstname'] . ' ' . $params['clientsdetails']['lastname'],
                'email' => $params['clientsdetails']['email'],
                'plan_unique_id' => 'whmcs', // Your WHMCS plan defined at Hyper Host, needs to be named whmcs
                'status' => 'active', // Activate this user
            ];

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://microweber.com/api/v1/sub_users",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: Bearer " . $params['serverpassword']
                ),
            ));

            $response = curl_exec($curl);
            $err      = curl_error($curl);

            curl_close($curl);

            if ($err) {

                return $err;

            } else {

                Capsule::table('tblcustomfieldsvalues')
                    ->where('fieldid', $microweber_field->id)
                    ->where('relid', $params['clientsdetails']['userid'])
                    ->update([
                        'value' => $response->microweber_id,
                    ]);

                return $response;

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

        $microweberId = microweber_id($params);

        $payload = [
            'platform' => $params['configoption1'],
            'domain' => $params['domain'],
            'microweber_id' => $microweberId,
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://microweber.com/api/v1/packages",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Accept: application/json",
                "Authorization: Bearer " . $params['serverpassword']
            ),
        ));

        $response = curl_exec($curl);
        $err      = curl_error($curl);

        curl_close($curl);

        if ($err) {

            return $err;

        } else {

            return 'success';

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

    try {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://microweber.com/api/v1/packages",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: Bearer " . $params['serverpassword']
            ),
        ));

        $response = curl_exec($curl);
        $err      = curl_error($curl);

        curl_close($curl);

        if ($err) {
            $errorMsg = $err;
        } else {
            $success = true;
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
