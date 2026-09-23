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
            ->with(['contractVersions' => fn ($query) => $query
                ->whereNotNull('published_at')
                ->where('effective_at', '<=', now())
                ->where('contract_service.is_required', true)
                ->orderByDesc('effective_at')])
            ->where('is_active', true)
            ->whereNotNull('cari_plus_product_id')
            ->orderBy('category_name')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Service $service): string => $service->category_name ?? 'Kategorisiz');

        return view('customer.services.index', [
            'groupedServices' => $services,
            'creditLimitKurus' => (int) ($request->user('customer')->credit_limit ?? 0),
            'lastSyncedAt' => Service::query()
                ->whereNotNull('cari_plus_product_id')
                ->whereNotNull('synced_at')
                ->latest('synced_at')
                ->first()?->synced_at,
            'canSync' => $cariPlus->isConfigured(),
            'canPurchase' => $cariPlus->isConfigured()
                && ($request->user('customer')->cari_plus_current_account_id !== null
                    || filled($request->user('customer')->cari_plus_current_account_code)),
        ]);
    }
}
