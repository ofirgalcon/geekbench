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
            $table->string('model_name')->nullable();
            $table->string('description')->nullable();
            $table->integer('samples')->nullable();
        });

        // Create indexes
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->index('model_name');
            $table->index('description');
            $table->index('samples');
        });
    }

    public function down()
    {
        $capsule = new Capsule();
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->dropColumn('model_name');
            $table->dropColumn('description');
            $table->dropColumn('samples');
        });
    }
}
