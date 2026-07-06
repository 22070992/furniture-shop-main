<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePassword;
use App\Http\Requests\ForgotPassword;
use App\Http\Requests\OTPVerification;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Models\UserVerify;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;


class AuthController extends Controller
{
    //
    public function login_ui()
    {
        return view('auth.login');
    }
    public function register_ui()
    {
        return view('auth.register');
    }
    public function forgot_password_ui()
    {
        return view('auth.forgot-password');
    }
    public function account_verification_ui(Request $request)
    {
        $user_id = $request->route('user_id');
        $user = User::find($user_id);
        if (!$user) {
            abort(404);
        }
        return view('auth.account-verification', ['user' => $user]);
    }

    private function createOrRefreshOtp(User $user): string
    {
        $otp = (string) rand(100000, 999999);
        $expiredTime = Carbon::now()->addMinutes(5);

        UserVerify::updateOrCreate(
            ['user_id' => $user->user_id],
            ['otp' => $otp, 'expired_time' => $expiredTime]
        );

        return $otp;
    }

    private function sendOtp(User $user, string $otp): void
    {
        session()->flash('verification_otp', $otp);

        if ($message = $this->mailConfigurationError()) {
            Log::warning('Account verification email is not configured for real delivery.', [
                'user_id' => $user->user_id,
                'email' => $user->email,
                'mailer' => config('mail.default'),
                'reason' => $message,
            ]);

            throw new RuntimeException($message);
        }

        try {
            Mail::raw('Your code is ' . $otp, function (Message $message) use ($user) {
                $message->to($user->email)
                    ->subject('Your Furemir verification code');
            });
        } catch (\Throwable $exception) {
            Log::error('Could not send account verification email.', [
                'user_id' => $user->user_id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Could not send the verification email. Please check the Gmail SMTP settings and try again.');
        }
    }

    private function mailConfigurationError(): ?string
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            return 'Email delivery is not configured for Gmail. Set MAIL_MAILER=smtp, MAIL_USERNAME and MAIL_PASSWORD in .env, then run php artisan optimize:clear.';
        }

        if ($mailer !== 'smtp') {
            return null;
        }

        $username = (string) config('mail.mailers.smtp.username');
        $password = (string) config('mail.mailers.smtp.password');
        $from = (string) config('mail.from.address');

        $missing = [];
        if (!filled(config('mail.mailers.smtp.host'))) {
            $missing[] = 'MAIL_HOST';
        }
        if (!filled(config('mail.mailers.smtp.port'))) {
            $missing[] = 'MAIL_PORT';
        }
        if (!filled($username)) {
            $missing[] = 'MAIL_USERNAME';
        }
        if (!filled($password) || $password === 'your_application_password') {
            $missing[] = 'MAIL_PASSWORD';
        }
        if (!filled($from) || str_ends_with($from, '.local')) {
            $missing[] = 'MAIL_FROM_ADDRESS';
        }

        if ($missing !== []) {
            return 'Gmail SMTP is missing required settings: ' . implode(', ', $missing) . '. Update .env and run php artisan optimize:clear.';
        }

        return null;
    }
    public function forgot_password_verification_ui(Request $request)
    {
        $user_id = $request->route('user_id');
        return view('auth.forgot-password-verification', ['user_id' => $user_id]);
    }
    public function login(Request $request)
    {
        // check user exists
        $user = User::where('email', $request->input('email'))->first();
        if ($user) {
            if ($user->is_active == false) return response()->json(['errors' => ['message' => ['This account is not active.']]], 400);
            if ($user->is_verified == false) return response()->json(['errors' => ['message' => ['This account is not verified.']]], 400);
            // check password
            if (Hash::check($request->input('password'), $user->password)) {
                if ($user->is_verified) {
                    // authenticated
                    Auth::login($user);
                    if ($user->is_staff)
                        return response()->json(['message' => 'Authenticated.'], 200);
                    else
                        return response()->json(['message' => 'Authenticated.'], 200);
                    // user haven't verified
                } else return response()->json(['errors' => ['message' => ['This account is not verified.']]], 400);
            } else {
                // invalid password
                return response()->json(['errors' => ['message' => ['Invalid password.']]], 400);
            }
        } else {
            // invalid email
            return response()->json(['errors' => ['message' => ['Invalid email.']]], 400);
        }
    }

    public function register(RegisterRequest $request)
    {
        // If RegisterRequest passes, create the user
        $user = User::where('email', $request->input('email'))->first();

        if ($user) {
            if ($user->is_verified) {
                return response()->json(['errors' => ['message' => ['The email is already exists.']]], 400);
            }
            $user->update([
                'password' => Hash::make($request->input('password')),
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
            ]);
            $otp = $this->createOrRefreshOtp($user);
            try {
                $this->sendOtp($user, $otp);
            } catch (RuntimeException $exception) {
                return response()->json(['errors' => ['message' => [$exception->getMessage()]]], 503);
            }

            // response
            return response()->json(['message' => 'User created.', 'user_id' => $user->user_id], 200);
        } else {
            $user = User::create([
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'avatar' => config('app.url') . 'images/default/default_avatar.jpg',
            ]);
            $otp = $this->createOrRefreshOtp($user);
            try {
                $this->sendOtp($user, $otp);
            } catch (RuntimeException $exception) {
                return response()->json(['errors' => ['message' => [$exception->getMessage()]]], 503);
            }

            // response
            return response()->json(['message' => 'User created.', 'user_id' => $user->user_id], 200);
        }
    }
    public function account_verification(OTPVerification $request)
    {
        // check existed account
        $user_id = $request->route('user_id');
        $user_verify = UserVerify::where('user_id', $user_id)
            ->where('otp', $request->input('otp'))
            ->where('expired_time', '>=', now())
            ->first();
        if ($user_verify) {
            $user = User::find($user_id);
            if ($user) {
                $user->update([
                    'is_verified' => true,
                ]);
                // authenticated
                Auth::login($user);
                $user_verify->delete();

                // response
                return redirect('/');
            }
            abort(500, 'User not found.');
            // ...
        }
        abort(500, 'Invalid Code.');
    }


    public function resend_otp(Request $request)
    {
        $user_id = $request->route('user_id');
        $user = User::find($user_id);

        if (!$user || $user->is_verified) {
            return back()->withErrors(['otp' => 'Could not find an unverified account for this request.'])->withInput($request->input());
        }

        $otp = $this->createOrRefreshOtp($user);

        try {
            $this->sendOtp($user, $otp);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['otp' => $exception->getMessage()])->withInput($request->input());
        }

        return back()->with('status', 'A new verification code has been sent to your email.');
    }
    public function logout()
    {
        Auth::logout();

        return redirect('/login');
    }
    public function forgot_password(ForgotPassword $request)
    {
        // check existed user
        $user = User::where('email', $request->input('email'))->where('is_active', true)->where('is_verified', true)->first();
        if ($user) {
            // generate new password
            $otp = rand(100000, 999999);
            $user->update(['password' => Hash::make($otp)]);
            // send email here
            Mail::raw('Your new password is ' . $otp, function ($message) use ($user) {
                $message->to($user->email);
            });
            // response
            return redirect('/forgot-password-verification');
        }
        // response
        return back()->withErrors(['email' => 'Cannot find user with this email.'])->withInput($request->input());
    }

    public function change_password(ChangePassword $request)
    {
        $user = User::find(Auth::user()->user_id);
        if (Hash::check($request->input('password'), $user->password)) {
            $user->update([
                'password' => Hash::make($request->input('new_password')),
            ]);
            return redirect('/');
        }
        return back()->withErrors(['password' => 'Invalid password'])->withInput($request->input());
    }
}
