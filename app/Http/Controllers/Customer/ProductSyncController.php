<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\CariPlusException;
use App\Http\Controllers\Controller;
use App\Services\Billing\SyncCariPlusProducts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductSyncController extends Controller
{
    public function __invoke(Request $request, SyncCariPlusProducts $syncProducts): JsonResponse|RedirectResponse
    {
        try {
            $count = $syncProducts->handle();
        } catch (CariPlusException $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 503);
            }

            return back()->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Cari Plus kataloğundaki {$count} ürün güncellendi.",
                'count' => $count,
            ]);
        }

        return back()->with('status', "Cari Plus kataloğundaki {$count} ürün güncellendi.");
    }
}
