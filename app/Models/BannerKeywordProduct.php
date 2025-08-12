<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannerKeywordProduct extends Model
{
    protected $fillable = [
        'banner_id',
        'keyword',
        'item_id',
    ];

    // Relationships
    public function banner()
    {
        return $this->belongsTo(Banner::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
