<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_visits')) {
            return;
        }

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id('site_visit_id');
            $table->string('session_id')->index();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->unsignedInteger('converted_order_id')->nullable()->index();
            $table->string('url');
            $table->string('referrer')->nullable();
            $table->string('source')->nullable()->index();
            $table->string('device', 40)->nullable()->index();
            $table->string('browser', 60)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('converted_order_id')->references('order_id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
    }
};
