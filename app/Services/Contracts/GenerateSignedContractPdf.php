<?php

namespace App\Services\Contracts;

use App\Exceptions\ContractSigningException;
use App\Models\ContractSigningChallenge;
use App\Models\Customer;
use App\Models\ServiceOrder;
use Carbon\CarbonInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

class GenerateSignedContractPdf
{
    public function __construct(private EncryptedContractDocumentStorage $documents) {}

    /** @return array{document_path: string, signed_document_hash: string} */
    public function generate(
        ServiceOrder $order,
        Customer $customer,
        ContractSigningChallenge $challenge,
        string $acceptanceUuid,
        CarbonInterface $acceptedAt,
    ): array {
        $order->loadMissing('contractVersion.contract');
        $disk = Storage::disk('local');
        try {
            $signatureBytes = base64_decode(Crypt::decryptString($disk->get($challenge->signature_path)), true);
        } catch (Throwable $exception) {
            throw new ContractSigningException('İmza dosyası çözülemedi.', previous: $exception);
        }

        if ($signatureBytes === false) {
            throw new ContractSigningException('İmza dosyası çözülemedi.');
        }

        if (! hash_equals($challenge->signature_hash, hash('sha256', $signatureBytes))) {
            throw new ContractSigningException('İmza dosyasının bütünlüğü doğrulanamadı.');
        }

        try {
            $sourceBytes = $this->documents->get($order->contractVersion->source_document_path);
        } catch (\RuntimeException $exception) {
            throw new ContractSigningException('Sözleşme dosyasının bütünlüğü doğrulanamadı.', previous: $exception);
        }

        if (! hash_equals($challenge->source_document_hash, hash('sha256', $sourceBytes))) {
            throw new ContractSigningException('Sözleşme dosyasının bütünlüğü doğrulanamadı.');
        }

        try {
            $options = new Options;
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml(view('pdf.contract-acceptance', [
                'acceptanceUuid' => $acceptanceUuid,
                'acceptedAt' => $acceptedAt,
                'challenge' => $challenge,
                'contractVersion' => $order->contractVersion,
                'contract' => $order->contractVersion->contract,
                'customer' => $customer,
                'order' => $order,
                'signatureData' => 'data:image/png;base64,'.base64_encode($signatureBytes),
            ])->render());
            $dompdf->setPaper('a4');
            $dompdf->render();

            $pdf = new Fpdi;
            $this->appendPdf($pdf, $sourceBytes);
            $this->appendPdf($pdf, $dompdf->output());
            $finalBytes = $pdf->Output('S');
            $documentPath = "contracts/acceptances/{$order->uuid}/{$acceptanceUuid}.pdf";
            $this->documents->put($documentPath, $finalBytes);

            return [
                'document_path' => $documentPath,
                'signed_document_hash' => hash('sha256', $finalBytes),
            ];
        } catch (ContractSigningException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ContractSigningException('İmzalı sözleşme PDF dosyası oluşturulamadı.', previous: $exception);
        }
    }

    private function appendPdf(Fpdi $output, string $bytes): void
    {
        $pageCount = $output->setSourceFile(StreamReader::createByString($bytes));

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $template = $output->importPage($pageNumber);
            $size = $output->getTemplateSize($template);
            $output->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $output->useTemplate($template);
        }
    }
}
