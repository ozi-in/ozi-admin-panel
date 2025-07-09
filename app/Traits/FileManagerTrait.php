<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

trait FileManagerTrait
{
    public static function uploadold(string $dir, string $format, $image = null): string
    {
        try {
            if ($image != null) {
                $imageName = Carbon::now()->toDateString() . "-" . uniqid() . "." . $format;
                if (!Storage::disk(self::getDisk())->exists($dir)) {
                    Storage::disk(self::getDisk())->makeDirectory($dir);
                }
                Storage::disk(self::getDisk())->putFileAs($dir, $image, $imageName);
            } else {
                $imageName = 'def.png';
            }
        } catch (\Exception $e) {
        }

        return $imageName;
    }
 public static function upload(string $dir, string $format, $image = null)
                                    {
                                        
                                        try {
                                            if ($image != null) {
                                                $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . "." . $format;
                                                $filePath = $image->getRealPath(); // ✅ full path to uploaded temp file
                                                $mimeType = $image->getMimeType();                                    
                                                $filePath = $image->getRealPath();
                                                $s3Client = Storage::disk('s3')->getClient();
                                                $result = $s3Client->putObject([
                                                    'Bucket'      => config('filesystems.disks.s3.bucket'),
                                                    'Key' => rtrim($dir, '/') . '/' . ltrim($imageName, '/'),
                                                    'Body'        => fopen($filePath, 'rb'),
                                                    'ContentType' => $mimeType,
                                                    // 'ACL' => 'public-read', // ❌ REMOVE if bucket owner enforced
                                                ]);
                                                
                                              
                                                if ($result) {
                                            return $imageName;
                                                } else {
                                                    throw new \Exception('S3 Upload failed: Storage::putFileAs returned false');
                                                }
                                            } else {
                                                return 'def.png';
                                            }
                                        } catch (\Exception $e) {
                                            \Log::error('Upload failed: ' . $e->getMessage());
                                            return null;
                                        }
                                    }
    public static function updateAndUpload(string $dir, $old_image, string $format, $image = null): mixed
    {
//        dd(self::getDisk());
        if ($image == null) {
            return $old_image;
        }
        try {
            if (Storage::disk(self::getDisk())->exists($dir . $old_image)) {
                Storage::disk(self::getDisk())->delete($dir . $old_image);
            }
        } catch (\Exception $e) {
        }
        return self::upload($dir, $format, $image);
    }

    public static function getDisk(): string
    {
        $config=\App\CentralLogics\Helpers::get_business_settings('local_storage');

        return isset($config)?($config==0?'s3':'public'):'public';
    }
}
