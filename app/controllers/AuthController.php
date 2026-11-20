<?php
/** FR-01 - sign in / sign out. */
class AuthController
{
    public static function login(): void
    {
        $error = null;
        if (is_post()) {
            verify_csrf();
            $username = trim((string) input('username'));
            $password = (string) input('password');

            if ($username === '' || $password === '') {
                $error = 'Please enter both your username and password.';
            } elseif (Auth::attempt($username, $password)) {
                flash('success', 'Welcome back, <strong>' . e(Auth::user()['full_name']) . '</strong>.');
                redirect('dashboard');
            } else {
                $error = 'Invalid username or password, or the account is inactive.';
                Audit::log('Authentication', 'FAILED-LOGIN', 'Failed sign-in for "' . $username . '"');
            }
        }
        view_bare('auth/login', ['error' => $error, 'username' => input('username', '')], 'Sign in');
    }

    public static function logout(): void
    {
        if (Auth::check()) {
            Auth::logout();
        }
        flash('info', 'You have been signed out.');
        redirect('login');
    }
}
