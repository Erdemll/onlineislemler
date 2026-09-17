<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ContractAcceptance;
use App\Models\ServiceOrder;
use App\Services\Contracts\VerifiedContractDocumentResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContractDocumentController extends Controller
{
    public function source(
        Request $request,
        ServiceOrder $serviceOrder,
        VerifiedContractDocumentResponse $documentResponse,
    ): Response {
        abort_unless($serviceOrder->customer_id === $request->user('customer')->id, 404);
        $serviceOrder->loadMissing('contractVersion');

        return $documentResponse->make(
            $serviceOrder->contractVersion->source_document_path,
            $serviceOrder->contractVersion->source_document_hash,
            'sozlesme-'.$serviceOrder->contractVersion->version.'.pdf',
            'inline',
        );
    }

    public function signed(
        Request $request,
        ContractAcceptance $contractAcceptance,
        VerifiedContractDocumentResponse $documentResponse,
    ): Response {
        abort_unless($contractAcceptance->customer_id === $request->user('customer')->id, 404);

        return $documentResponse->make(
            $contractAcceptance->document_path,
            $contractAcceptance->signed_document_hash,
            'imzali-sozlesme-'.$contractAcceptance->uuid.'.pdf',
            'attachment',
        );
    }
}
