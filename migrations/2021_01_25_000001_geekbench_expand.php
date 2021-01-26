<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

class GeekbenchExpand extends Migration
{
    private $tableName = 'geekbench';

    public function up()
    {
        $capsule = new Capsule();
        
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->bigInteger('metal_score')->nullable();
            $table->bigInteger('metal_samples')->nullable();
        });

        // Create indexes
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->index('metal_score');
            $table->index('metal_samples');
        });
    }

    public function down()
    {
        $capsule = new Capsule();
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->dropColumn('metal_score');
            $table->dropColumn('metal_samples');
        });
    }
}
