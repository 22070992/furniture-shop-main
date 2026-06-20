<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VisualFeedback;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisualFeedbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('visual_feedback_activities');
        Schema::dropIfExists('visual_feedback_attachments');
        Schema::dropIfExists('visual_feedback_comments');
        Schema::dropIfExists('visual_feedbacks');
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('email');
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('avatar')->default('/images/default_avatar.jpg');
            $table->date('birth_date')->nullable();
            $table->boolean('gender')->nullable();
            $table->boolean('is_staff')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_test_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_test_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_test_primary');
        });

        Schema::create('visual_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('type', 80);
            $table->string('priority', 30)->default('Medium');
            $table->string('status', 40)->default('Open');
            $table->string('title', 180);
            $table->text('description');
            $table->text('expected_result')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('page_url', 2048);
            $table->string('page_path', 1024)->nullable();
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
        });

        Schema::create('visual_feedback_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('feedback_id');
            $table->unsignedInteger('user_id');
            $table->text('comment');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });

        Schema::create('visual_feedback_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('feedback_id');
            $table->unsignedInteger('user_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();
        });

        Schema::create('visual_feedback_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('feedback_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 120);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->timestamps();
        });
    }

    private function user(bool $staff = false): User
    {
        return User::create([
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'first_name' => $staff ? 'Admin' : 'Customer',
            'last_name' => fake()->lastName(),
            'gender' => 1,
            'is_staff' => $staff,
            'is_verified' => true,
            'is_active' => true,
        ]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'type' => 'Bug',
            'priority' => 'High',
            'title' => 'Product image is wrong',
            'description' => 'The selected product image does not match the product name.',
            'expected_result' => 'Display the correct product image.',
            'page_url' => 'http://localhost:8000/shop',
            'page_path' => '/shop',
            'page_title' => 'Shop',
            'element_tag' => 'img',
            'element_text' => '',
            'element_id' => '',
            'element_classes' => 'product-image',
            'css_selector' => '[data-feedback-id="shop.product-image.1"]',
            'xpath' => '/html[1]/body[1]/main[1]/img[1]',
            'element_attributes' => ['data-feedback-id' => 'shop.product-image.1', 'src' => '/images/chair.jpg'],
            'element_rect' => ['x' => 10, 'y' => 20, 'width' => 300, 'height' => 200],
            'scroll_position' => ['x' => 0, 'y' => 120],
            'viewport' => ['width' => 1440, 'height' => 900],
            'device_type' => 'desktop',
            'browser_info' => ['user_agent' => 'Feature test'],
            'screenshot_data' => 'data:image/png;base64,' . base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')),
        ], $override);
    }

    private function feedbackFor(User $user, array $override = []): VisualFeedback
    {
        return VisualFeedback::create(array_merge($this->payload(), [
            'user_id' => $user->getKey(),
            'status' => 'Open',
            'created_by_role' => $user->is_staff ? 'admin' : 'user',
            'screenshot_data' => null,
        ], $override));
    }

    public function test_user_can_create_feedback_with_screenshot(): void
    {
        Storage::fake('local');
        $user = $this->user();

        $response = $this->actingAs($user)->postJson(route('feedback.store'), $this->payload());

        $response->assertCreated()->assertJsonPath('feedback_id', 1);
        $feedback = VisualFeedback::first();

        $this->assertSame($user->getKey(), $feedback->user_id);
        $this->assertSame('Open', $feedback->status);
        $this->assertSame('[data-feedback-id="shop.product-image.1"]', $feedback->css_selector);
        Storage::disk('local')->assertExists($feedback->screenshot_path);
    }

    public function test_guest_cannot_submit_feedback_directly(): void
    {
        $this->postJson(route('feedback.store'), $this->payload())->assertUnauthorized();
    }

    public function test_user_can_only_view_own_feedback(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $feedback = $this->feedbackFor($owner);

        $this->actingAs($other)->get(route('feedback.show', $feedback))->assertForbidden();
    }

    public function test_user_cannot_change_feedback_status(): void
    {
        $user = $this->user();
        $feedback = $this->feedbackFor($user);

        $this->actingAs($user)
            ->patch(route('admin.feedback.update', $feedback), ['status' => 'Resolved'])
            ->assertRedirect(route('login'));

        $this->assertSame('Open', $feedback->fresh()->status);
    }

    public function test_user_cannot_see_internal_comment(): void
    {
        $user = $this->user();
        $feedback = $this->feedbackFor($user);
        $feedback->comments()->create([
            'user_id' => $user->getKey(),
            'comment' => 'Visible public comment',
            'is_internal' => false,
        ]);
        $feedback->comments()->create([
            'user_id' => $user->getKey(),
            'comment' => 'Internal admin note',
            'is_internal' => true,
        ]);

        $this->actingAs($user)
            ->get(route('feedback.show', $feedback))
            ->assertOk()
            ->assertSee('Visible public comment')
            ->assertDontSee('Internal admin note');
    }

    public function test_admin_can_view_all_feedback(): void
    {
        $admin = $this->user(true);
        $customer = $this->user();
        $this->feedbackFor($customer, ['title' => 'Customer feedback']);

        $this->actingAs($admin)
            ->get(route('admin.feedback.index'))
            ->assertOk()
            ->assertSee('Customer feedback');
    }

    public function test_admin_can_update_status_and_assign_feedback(): void
    {
        $admin = $this->user(true);
        $customer = $this->user();
        $feedback = $this->feedbackFor($customer);

        $this->actingAs($admin)
            ->patch(route('admin.feedback.update', $feedback), [
                'status' => 'Resolved',
                'priority' => 'Critical',
                'assigned_to' => $admin->getKey(),
            ])
            ->assertRedirect();

        $feedback->refresh();
        $this->assertSame('Resolved', $feedback->status);
        $this->assertSame('Critical', $feedback->priority);
        $this->assertSame($admin->getKey(), $feedback->assigned_to);
        $this->assertNotNull($feedback->resolved_at);
    }

    public function test_admin_can_create_internal_comment(): void
    {
        $admin = $this->user(true);
        $customer = $this->user();
        $feedback = $this->feedbackFor($customer);

        $this->actingAs($admin)
            ->post(route('admin.feedback.comments.store', $feedback), [
                'comment' => 'Only admins should see this.',
                'is_internal' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($feedback->comments()->first()->is_internal);
    }

    public function test_validation_rejects_missing_required_data(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->postJson(route('feedback.store'), $this->payload(['title' => '', 'css_selector' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'css_selector']);
    }

    public function test_attachment_size_is_limited(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('feedback.store'), $this->payload([
                'attachment' => UploadedFile::fake()->create('too-large.pdf', 11000, 'application/pdf'),
            ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attachment']);
    }

    public function test_selector_and_description_are_saved(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson(route('feedback.store'), $this->payload([
            'description' => 'Selector storage check',
            'css_selector' => '[data-feedback-id="home.hero.title"]',
        ]));

        $this->assertDatabaseHas('visual_feedbacks', [
            'description' => 'Selector storage check',
            'css_selector' => '[data-feedback-id="home.hero.title"]',
        ]);
    }

    public function test_frontend_feedback_mode_blocks_default_clicks(): void
    {
        $script = file_get_contents(public_path('js/visual_feedback.js'));

        $this->assertStringContainsString('preventDefault()', $script);
        $this->assertStringContainsString('stopImmediatePropagation()', $script);
    }

    public function test_feedback_ui_has_mobile_breakpoint(): void
    {
        $css = file_get_contents(public_path('css/visual_feedback.css'));

        $this->assertStringContainsString('@media (max-width: 767px)', $css);
    }

    public function test_ai_chatbox_is_positioned_above_feedback_button(): void
    {
        $css = file_get_contents(public_path('css/custom.css'));

        $this->assertStringContainsString('bottom: calc(96px + env(safe-area-inset-bottom));', $css);
        $this->assertStringContainsString('z-index: 99910;', $css);
        $this->assertStringContainsString('height: min(580px, calc(100vh - 210px));', $css);
        $this->assertStringContainsString('bottom: calc(84px + env(safe-area-inset-bottom));', $css);
        $this->assertStringContainsString('height: min(560px, calc(100vh - 190px));', $css);
    }

    public function test_back_to_top_button_is_on_left_side(): void
    {
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertStringContainsString('#scrollUp {', $css);
        $this->assertStringContainsString('left: 30px;', $css);
        $this->assertStringContainsString('right: auto;', $css);
        $this->assertStringContainsString('left: 20px;', $css);
    }

    public function test_shop_list_view_renders_products_and_ajax_updates_it(): void
    {
        $view = file_get_contents(resource_path('views/pages/shop/index.blade.php'));
        $script = file_get_contents(public_path('js/shop_api.js'));

        $this->assertStringContainsString('id="product-list-view"', $view);
        $this->assertStringContainsString('shop.product-list-card.', $view);
        $this->assertStringContainsString('function createProductListElement(product)', $script);
        $this->assertStringContainsString("$('#product-list-view').html(listHtml);", $script);
    }
}
