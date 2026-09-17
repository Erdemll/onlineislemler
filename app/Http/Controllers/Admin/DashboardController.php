<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractAcceptance;
use App\Models\ContractVersion;
use App\Models\Service;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'contractCount' => Contract::query()->count(),
            'versionCount' => ContractVersion::query()->count(),
            'acceptanceCount' => ContractAcceptance::query()->count(),
            'productCount' => Service::query()->whereNotNull('cari_plus_product_id')->count(),
            'recentAcceptances' => ContractAcceptance::query()
                ->with(['customer:id,first_name,last_name,email', 'serviceOrder:id,service_name_snapshot'])
                ->latest('accepted_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
