<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContractAcceptance;
use App\Models\ContractVersion;
use App\Services\Contracts\VerifiedContractDocumentResponse;
use Illuminate\Http\Response;

class ContractDocumentController extends Controller
{
    public function source(
        ContractVersion $contractVersion,
        VerifiedContractDocumentResponse $documentResponse,
    ): Response {
        return $documentResponse->make(
            $contractVersion->source_document_path,
            $contractVersion->source_document_hash,
            'sozlesme-'.$contractVersion->uuid.'.pdf',
        );
    }

    public function signed(
        ContractAcceptance $contractAcceptance,
        VerifiedContractDocumentResponse $documentResponse,
    ): Response {
        return $documentResponse->make(
            $contractAcceptance->document_path,
            $contractAcceptance->signed_document_hash,
            'imzali-sozlesme-'.$contractAcceptance->uuid.'.pdf',
        );
    }
}
