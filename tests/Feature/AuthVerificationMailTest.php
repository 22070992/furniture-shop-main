<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserVerify;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthVerificationMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('user_verifies');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('email');
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('avatar')->nullable();
            $table->date('birth_date')->nullable();
            $table->unsignedInteger('gender')->nullable();
            $table->boolean('is_staff')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });

        Schema::create('user_verifies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('otp');
            $table->timestamp('expired_time');
            $table->timestamps();
        });
    }

    public function test_register_reports_when_real_email_delivery_is_not_configured(): void
    {
        config(['mail.default' => 'log']);

        $this->post('/register', $this->registerPayload('missing-mail-config@example.test'))
            ->assertStatus(503)
            ->assertJsonFragment([
                'Email delivery is not configured for Gmail. Set MAIL_MAILER=smtp, MAIL_USERNAME and MAIL_PASSWORD in .env, then run php artisan optimize:clear.',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'missing-mail-config@example.test',
            'is_verified' => false,
        ]);
        $this->assertSame(1, UserVerify::count());
    }

    public function test_register_creates_verification_code_when_smtp_is_configured(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => 'sender@example.test',
            'mail.mailers.smtp.password' => 'app-password',
            'mail.from.address' => 'sender@example.test',
            'mail.from.name' => 'Furemir',
        ]);

        $response = $this->post('/register', $this->registerPayload('configured-mail@example.test'));

        $response->assertOk()
            ->assertJsonStructure(['message', 'user_id']);

        $user = User::where('email', 'configured-mail@example.test')->firstOrFail();
        $this->assertDatabaseHas('user_verifies', [
            'user_id' => $user->user_id,
        ]);
    }

    public function test_resend_refreshes_verification_code_for_unverified_user(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => 'sender@example.test',
            'mail.mailers.smtp.password' => 'app-password',
            'mail.from.address' => 'sender@example.test',
            'mail.from.name' => 'Furemir',
        ]);

        $user = User::create([
            'email' => 'resend@example.test',
            'password' => bcrypt('secret123'),
            'first_name' => 'Hung',
            'last_name' => 'Do',
            'is_verified' => false,
        ]);
        UserVerify::create([
            'user_id' => $user->user_id,
            'otp' => '111111',
            'expired_time' => now()->addMinutes(5),
        ]);

        $this->get('/resend-otp/' . $user->user_id)
            ->assertRedirect()
            ->assertSessionHas('status', 'A new verification code has been sent to your email.');

        $this->assertNotSame('111111', UserVerify::where('user_id', $user->user_id)->first()?->otp);
    }

    public function test_resend_reports_mail_configuration_error(): void
    {
        config(['mail.default' => 'log']);

        $user = User::create([
            'email' => 'resend-missing-config@example.test',
            'password' => bcrypt('secret123'),
            'first_name' => 'Hung',
            'last_name' => 'Do',
            'is_verified' => false,
        ]);

        $this->get('/resend-otp/' . $user->user_id)
            ->assertRedirect()
            ->assertSessionHasErrors('otp');

        $this->assertSame(1, UserVerify::where('user_id', $user->user_id)->count());
    }

    private function registerPayload(string $email): array
    {
        return [
            'first_name' => 'Hung',
            'last_name' => 'Do',
            'email' => $email,
            'password' => 'secret123',
        ];
    }
}
