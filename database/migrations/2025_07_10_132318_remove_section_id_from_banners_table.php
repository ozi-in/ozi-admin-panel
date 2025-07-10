<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Change existing int column to json
        Schema::table('banners', function (Blueprint $table) {
            $table->json('section_id')->nullable()->change();
        });

        // Optional: convert old int values to json arrays (e.g. 1 => ["1"])
        \DB::table('banners')->get()->each(function ($banner) {
            if (is_numeric($banner->section_id)) {
                \DB::table('banners')
                    ->where('id', $banner->id)
                    ->update(['section_id' => json_encode([(string) $banner->section_id])]);
            }
        });
    }

    public function down()
    {
        // Rollback: convert JSON back to int (use the first item)
        \DB::table('banners')->get()->each(function ($banner) {
            $ids = json_decode($banner->section_id, true);
            $first = is_array($ids) && count($ids) ? (int)$ids[0] : null;
            \DB::table('banners')->where('id', $banner->id)->update(['section_id' => $first]);
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->unsignedBigInteger('section_id')->nullable()->change();
        });
    }
};
