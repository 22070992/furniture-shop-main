<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_combo_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('model', 120)->default('furemir-rule-assisted');
            $table->unsignedTinyInteger('max_products')->default(5);
            $table->unsignedTinyInteger('candidate_limit')->default(24);
            $table->decimal('budget_overage_percent', 5, 2)->default(10);
            $table->json('room_category_mapping')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('enabled');
            $table->foreign('updated_by')->references('user_id')->on('users')->nullOnDelete();
        });

        DB::table('ai_combo_settings')->insert([
            'enabled' => config('shop.ai_interior_combo.enabled', true),
            'model' => config('shop.ai_interior_combo.model', 'furemir-rule-assisted'),
            'max_products' => config('shop.ai_interior_combo.max_products', 5),
            'candidate_limit' => config('shop.ai_interior_combo.candidate_limit', 24),
            'budget_overage_percent' => config('shop.ai_interior_combo.budget_overage_percent', 10),
            'room_category_mapping' => json_encode(config('shop.ai_interior_combo.room_category_mapping', [])),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_combo_settings');
    }
};
