<?php

namespace App\Http\Controllers\Customer;

use App\Contracts\CariPlusGateway;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request, CariPlusGateway $cariPlus): View
    {
        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->orderBy('id')
            ->get();

        return view('customer.services.index', [
            'services' => $services,
            'canPurchase' => $cariPlus->isConfigured()
                && ($request->user('customer')->cari_plus_current_account_id !== null
                    || filled($request->user('customer')->cari_plus_current_account_code)),
        ]);
    }
}
