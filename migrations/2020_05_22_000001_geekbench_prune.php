<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

class GeekbenchPrune extends Migration
{
    private $tableName = 'geekbench';

    public function up()
    {
        $capsule = new Capsule();
        
        // Drop cache columns
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->dropColumn(['mac_benchmarks', 'cuda_benchmarks', 'opencl_benchmarks']);
        });

        // Rename last_cache_pull column
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->renameColumn('last_cache_pull', 'last_run');
        });
    }

    public function down()
    {
        $capsule = new Capsule();

        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->bigInteger('last_cache_pull')->nullable();
            $table->mediumText('mac_benchmarks')->nullable();
            $table->mediumText('cuda_benchmarks')->nullable();
            $table->mediumText('opencl_benchmarks')->nullable();
        });

        // Rename last_run column
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->renameColumn('last_run', 'last_cache_pull');
        });
    }
}