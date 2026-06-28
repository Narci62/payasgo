<?php

namespace App\Http\Controllers;

use Google\Client;
use Google\Service\AndroidEnterprise;
use Google\Service\AndroidEnterprise\AdministratorWebTokenSpec;
use Google\Service\AndroidEnterprise\AdministratorWebTokenSpecPlaySearch;

class ManagedPlayController extends Controller
{
    public function getIframeUrl()
    {
        try {

            $client = new Client();
            $client->setAuthConfig(config('services.amapi.service_account_json'));
            $client->addScope(AndroidEnterprise::ANDROIDENTERPRISE);

            $service = new AndroidEnterprise($client);

            $spec = new AdministratorWebTokenSpec();
            $spec->setParent(url('/admin/mdm/apps'));

            // Active la recherche d'applications
            $playSearch = new AdministratorWebTokenSpecPlaySearch();
            $playSearch->setEnabled(true);

            // Facultatif : autoriser l'approbation directement
            $playSearch->setApproveApps(true);

            $spec->setPlaySearch($playSearch);

            $response = $service->enterprises->createWebToken(
                config('services.amapi.enterprise_id'),
                $spec
            );

            return view('admin.play_iframe', [
                'token' => $response->getToken(),
            ]);

        } catch (\Throwable $e) {
            dd($e);
        }
    }
}
