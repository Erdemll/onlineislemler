<?php

namespace App\Console\Commands;

use App\Models\ContractAcceptance;
use App\Models\ContractVersion;
use App\Services\Contracts\EncryptedContractDocumentStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contracts:encrypt-documents')]
#[Description('Mevcut sözleşme belgelerini bütünlük kontrolüyle yerinde şifreler')]
class EncryptContractDocuments extends Command
{
    public function handle(EncryptedContractDocumentStorage $documents): int
    {
        $encrypted = 0;

        ContractVersion::query()->orderBy('id')->each(function (ContractVersion $version) use ($documents, &$encrypted): void {
            if ($documents->encryptExisting($version->source_document_path, $version->source_document_hash)) {
                $encrypted++;
            }
        });
        ContractAcceptance::query()->orderBy('id')->each(function (ContractAcceptance $acceptance) use ($documents, &$encrypted): void {
            if ($documents->encryptExisting($acceptance->document_path, $acceptance->signed_document_hash)) {
                $encrypted++;
            }
        });

        $this->components->info("{$encrypted} sözleşme belgesi şifrelendi.");

        return self::SUCCESS;
    }
}
