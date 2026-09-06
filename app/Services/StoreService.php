<?php
namespace App\Services;

use App\Models\Store;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

final class StoreService
{
    public function create(int $resellerId, string $name, string $brandColor, ?UploadedFile $logo = null): Store
    {
        return Store::query()->create([
            'reseller_id' => $resellerId,
            'name' => trim($name),
            'brand_color' => $this->color($brandColor),
            'logo_path' => $logo ? $this->storeLogo($resellerId, $logo) : null,
        ]);
    }

    public function update(Store $store, string $name, string $brandColor, ?UploadedFile $logo = null): Store
    {
        $store->name = trim($name);
        $store->brand_color = $this->color($brandColor);
        if ($logo) $store->logo_path = $this->storeLogo((int) $store->reseller_id, $logo);
        $store->save();
        return $store->fresh();
    }

    private function color(string $value): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^#[0-9a-f]{6}$/D', $value)) throw new InvalidArgumentException('invalid_brand_color');
        return $value;
    }

    private function storeLogo(int $resellerId, UploadedFile $file): string
    {
        if (!$file->isValid() || $file->getSize() > 2 * 1024 * 1024) throw new InvalidArgumentException('invalid_store_logo');
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $mime = $file->getMimeType();
        if (!isset($extensions[$mime])) throw new InvalidArgumentException('invalid_store_logo_type');
        $relativeDir = 'uploads/stores/'.$resellerId;
        $dir = public_path($relativeDir);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('store_logo_directory_failed');
        $name = bin2hex(random_bytes(20)).'.'.$extensions[$mime];
        $file->move($dir, $name);
        return '/'.$relativeDir.'/'.$name;
    }
}
