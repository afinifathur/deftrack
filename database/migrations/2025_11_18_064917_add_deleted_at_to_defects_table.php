<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeletedAtToDefectsTable extends Migration
{
    public function up()
    {
        Schema::table('defects', function (Blueprint $table) {
            // nullable timestamp for soft deletes
            $table->softDeletes(); // shorthand adds nullable deleted_at TIMESTAMP
        });
    }

    public function down()
    {
        Schema::table('defects', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
