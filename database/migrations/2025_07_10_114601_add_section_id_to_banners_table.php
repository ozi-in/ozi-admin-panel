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
    Schema::table('banners', function (Blueprint $table) {
        $table->unsignedInteger('section_id')->nullable()->after('default_link');
    });
}

public function down()
{
    Schema::table('banners', function (Blueprint $table) {
        $table->dropColumn('section_id');
    });
}
};
