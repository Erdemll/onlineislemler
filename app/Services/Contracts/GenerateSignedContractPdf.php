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
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Throwable;

class GenerateSignedContractPdf
{
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
        $sourcePath = $disk->path($order->contractVersion->source_document_path);
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

        $sourceBytes = $disk->get($order->contractVersion->source_document_path);

        if (! hash_equals($challenge->source_document_hash, hash('sha256', $sourceBytes))) {
            throw new ContractSigningException('Sözleşme dosyasının bütünlüğü doğrulanamadı.');
        }

        $appendixPath = tempnam(sys_get_temp_dir(), 'contract-appendix-');

        if ($appendixPath === false) {
            throw new ContractSigningException('PDF için geçici dosya oluşturulamadı.');
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

            if (file_put_contents($appendixPath, $dompdf->output()) === false) {
                throw new RuntimeException('Kabul sayfası geçici dosyaya yazılamadı.');
            }

            $pdf = new Fpdi;
            $this->appendPdf($pdf, $sourcePath);
            $this->appendPdf($pdf, $appendixPath);
            $finalBytes = $pdf->Output('S');
            $documentPath = "contracts/acceptances/{$order->uuid}/{$acceptanceUuid}.pdf";

            if (! $disk->put($documentPath, $finalBytes)) {
                throw new RuntimeException('İmzalı sözleşme güvenli depolamaya yazılamadı.');
            }

            return [
                'document_path' => $documentPath,
                'signed_document_hash' => hash('sha256', $finalBytes),
            ];
        } catch (ContractSigningException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ContractSigningException('İmzalı sözleşme PDF dosyası oluşturulamadı.', previous: $exception);
        } finally {
            @unlink($appendixPath);
        }
    }

    private function appendPdf(Fpdi $output, string $path): void
    {
        $pageCount = $output->setSourceFile($path);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $template = $output->importPage($pageNumber);
            $size = $output->getTemplateSize($template);
            $output->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $output->useTemplate($template);
        }
    }
}
