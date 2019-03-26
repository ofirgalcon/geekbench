<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

class GeekbenchCaching extends Migration
{
    private $tableName = 'geekbench';

    public function up()
    {
        $capsule = new Capsule();
        
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            
            // Add new columns
            $table->bigInteger('cuda_score')->nullable();
            $table->bigInteger('cuda_samples')->nullable();
            $table->bigInteger('opencl_score')->nullable();
            $table->bigInteger('opencl_samples')->nullable();
            $table->string('gpu_name')->nullable();
            $table->bigInteger('last_cache_pull')->nullable();
            $table->mediumText('mac_benchmarks')->nullable();
            $table->mediumText('cuda_benchmarks')->nullable();
            $table->mediumText('opencl_benchmarks')->nullable();
            
            // Create index
            $table->index('gpu_name');

            // Change existing columns
            $table->integer('score')->nullable()->change();
            $table->integer('multiscore')->nullable()->change();
        });
    }

    public function down()
    {
        $capsule = new Capsule();
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->dropColumn('cuda_score');
            $table->dropColumn('cuda_samples');
            $table->dropColumn('opencl_score');
            $table->dropColumn('opencl_samples');
            $table->dropColumn('gpu_name');
            $table->dropColumn('last_cache_pull');
            $table->dropColumn('mac_benchmarks');
            $table->dropColumn('cuda_benchmarks');
            $table->dropColumn('opencl_benchmarks');
        });
    }
}