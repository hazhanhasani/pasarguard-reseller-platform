<?php
namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Models\Provider;
use App\Services\DiagnosticBundleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use ZipArchive;

class DiagnosticBundleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_bundle_contains_health_but_not_provider_or_payment_secrets(): void
    {
        $providerSecret = 'provider-secret-'.bin2hex(random_bytes(12));
        $paymentSecret = 'blu_live_'.bin2hex(random_bytes(16));
        Provider::query()->create([
            'name'=>'Internal Provider',
            'api_url'=>'https://provider.example.test',
            'credentials'=>['key'=>$providerSecret],
            'group_ids'=>[1],
            'mode'=>'disabled',
            'health'=>'unknown',
        ]);
        PaymentGatewaySetting::query()->create([
            'gateway'=>'blupal',
            'enabled'=>true,
            'credentials'=>['api_key'=>$paymentSecret],
            'health'=>'unknown',
        ]);

        $path = app(DiagnosticBundleService::class)->create();
        try {
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($path) === true);
            $combined = '';
            for ($i=0; $i<$zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                $content = $zip->getFromIndex($i);
                if (is_string($content)) $combined .= "\n".$name."\n".$content;
            }
            $zip->close();
            $this->assertStringContainsString('system-health.json', $combined);
            $this->assertStringContainsString('Internal Provider', $combined);
            $this->assertStringNotContainsString($providerSecret, $combined);
            $this->assertStringNotContainsString($paymentSecret, $combined);
            $this->assertStringNotContainsString('credentials', strtolower($combined));
        } finally { if (is_file($path)) @unlink($path); }
    }
}
