<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visual_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('assigned_to')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('visual_feedbacks')->nullOnDelete();
            $table->string('type', 80);
            $table->string('priority', 30)->default('Medium');
            $table->string('status', 40)->default('Open');
            $table->string('title', 180);
            $table->text('description');
            $table->text('expected_result')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('page_url', 2048);
            $table->string('page_path', 512)->nullable();
            $table->string('page_title')->nullable();
            $table->string('route_name')->nullable();
            $table->string('element_tag', 80)->nullable();
            $table->text('element_text')->nullable();
            $table->string('element_id')->nullable();
            $table->text('element_classes')->nullable();
            $table->string('css_selector', 2048);
            $table->text('xpath')->nullable();
            $table->json('element_attributes')->nullable();
            $table->json('element_rect')->nullable();
            $table->json('scroll_position')->nullable();
            $table->json('viewport')->nullable();
            $table->string('device_type', 40)->nullable();
            $table->json('browser_info')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->string('created_by_role', 80)->default('user');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_to')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['status', 'priority']);
            $table->index('type');
            $table->index('created_at');
            $table->index('page_path');
            $table->index('assigned_to');
        });

        Schema::create('visual_feedback_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('visual_feedbacks')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->text('comment');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['feedback_id', 'is_internal']);
        });

        Schema::create('visual_feedback_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('visual_feedbacks')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index('feedback_id');
        });

        Schema::create('visual_feedback_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('visual_feedbacks')->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 120);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['feedback_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visual_feedback_activities');
        Schema::dropIfExists('visual_feedback_attachments');
        Schema::dropIfExists('visual_feedback_comments');
        Schema::dropIfExists('visual_feedbacks');
    }
};
