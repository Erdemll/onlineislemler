<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\CariPlusException;
use App\Http\Controllers\Controller;
use App\Services\Billing\SyncCariPlusProducts;
use Illuminate\Http\RedirectResponse;

class ProductSyncController extends Controller
{
    public function __invoke(SyncCariPlusProducts $syncProducts): RedirectResponse
    {
        try {
            $count = $syncProducts->handle();
        } catch (CariPlusException $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', "Cari Plus kataloğundaki {$count} ürün güncellendi.");
    }
}
