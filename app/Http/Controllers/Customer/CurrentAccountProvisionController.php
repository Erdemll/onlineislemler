<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\CariPlusException;
use App\Http\Controllers\Controller;
use App\Services\CariPlus\ProvisionCurrentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentAccountProvisionController extends Controller
{
    public function __invoke(Request $request, ProvisionCurrentAccount $provision): RedirectResponse
    {
        try {
            $provision->handle($request->user('customer'));
        } catch (CariPlusException $exception) {
            report($exception);

            return back()->withErrors([
                'provision' => 'Müşteri hesabınız şu anda oluşturulamadı. Lütfen tekrar deneyin.',
            ]);
        }

        return redirect()->route('customer.dashboard')->with('status', 'Müşteri hesabınız kullanıma hazır.');
    }
}
