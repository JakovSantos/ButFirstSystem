<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Models\User;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * Sign-in and sign-out.
 */
final class AuthController
{
    /** bcrypt hash of a random string, only used to keep failed logins as slow as real ones. */
    private const DUMMY_HASH = '$2y$10$PZ6p9DHJ2nGqXzxhKmE5h.9HOZq6vTSvgvJMuE2T1Nt6l1aVzXxKe';

    public function show(): void
    {
        $user = Session::get('user');
        if ($user !== null) {
            Response::redirect(($user['role'] ?? '') === 'admin' ? '/inventory' : '/payroll');
        }

        View::display('login', [
            'title'    => 'But First System',
            'username' => (string) (Session::pullInput()['username'] ?? ''),
        ], 'layout');
    }

    public function login(): void
    {
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        // The password is used exactly as typed (no trim): spaces can be part of a password.
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($username === '' || $password === '' || mb_strlen($username) > 50 || strlen($password) > 200) {
            Session::flashInput(['username' => mb_substr($username, 0, 50)]);
            Session::flash('Please enter both username and password.', 'Login Error', 'warning');
            Response::redirect('/login');
        }

        // Brute-force protection: too many failures for this username from this address locks it for a while.
        $limitKey = 'login|' . mb_strtolower($username) . '|' . Request::capture()->ip();
        $maxAttempts = (int) App::config('security.login_max_attempts', 5);
        if (RateLimiter::tooManyAttempts($limitKey, $maxAttempts)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($limitKey) / 60));
            Logger::info("Locked login for '{$username}'");
            Session::flashInput(['username' => $username]);
            Session::flash("Too many failed sign-in attempts.\nPlease try again in {$minutes} minute(s).", 'Account Locked', 'error');
            Response::redirect('/login');
        }

        // Case-insensitive username (admin, or an Employee ID such as F-001), case-sensitive password.
        $account = User::findByUsername($username);

        // A missing account still runs password_verify on a dummy hash, so the response time
        // does not reveal which usernames exist.
        $hash = $account !== null ? (string) $account->password_hash : self::DUMMY_HASH;
        $valid = password_verify($password, $hash) && $account !== null;

        if (! $valid) {
            $attempts = RateLimiter::hit($limitKey, (int) App::config('security.login_lockout_seconds', 900));
            Logger::info("Failed login for '{$username}' ({$attempts}/{$maxAttempts})");
            Session::flashInput(['username' => $username]);
            $left = $maxAttempts - $attempts;
            Session::flash(
                'Invalid username or password.' . ($left > 0 && $left <= 2 ? "\n{$left} attempt(s) left before the account is locked for a while." : ''),
                'Login Failed',
                'error'
            );
            Response::redirect('/login');
        }
        RateLimiter::clear($limitKey);

        // Old hashes are upgraded when PHP's default algorithm gets stronger.
        if (password_needs_rehash((string) $account->password_hash, PASSWORD_DEFAULT)) {
            $account->password_hash = password_hash($password, PASSWORD_DEFAULT);
            $account->save();
        }

        $name = $account->role === 'employee' ? (string) ($account->payroll?->name ?? $account->username) : 'Admin';

        Session::regenerate();
        Session::put('user', [
            'id'          => $account->id,
            'username'    => $account->username,
            'role'        => $account->role,
            'employee_id' => $account->employee_id,
            'name'        => $name,
        ]);
        Logger::info("Login '{$account->username}' ({$account->role})");

        // A new or reset account sets its own password first.
        if ($account->must_change_password) {
            Session::flash(
                "Welcome, {$name}! Please set your own password to finish signing in.",
                'Set Your Password',
                'information'
            );
            Response::redirect('/account');
        }

        // Employees land on their own payroll; admins on the inventory.
        Response::redirect($account->role === 'admin' ? '/inventory' : '/payroll');
    }

    public function logout(): void
    {
        Session::destroy();
        Response::redirect('/login');
    }
}
