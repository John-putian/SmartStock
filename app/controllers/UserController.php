<?php
/** FR-01 - user account management (Administrator / Manager only). */
class UserController
{
    public static function index(): void
    {
        view('users/index', ['users' => User::all()], 'User Accounts');
    }

    public static function save(): void
    {
        verify_csrf();
        $id       = (int) input('id', 0);
        $fullName = trim((string) input('full_name', ''));
        $username = trim((string) input('username', ''));
        $password = (string) input('password', '');
        $role     = in_array(input('role'), Auth::ROLES, true) ? (string) input('role') : 'cashier';
        $status   = input('status') === 'inactive' ? 'inactive' : 'active';
        $contact  = trim((string) input('contact_no', '')) ?: null;

        $errors = [];
        if ($fullName === '')                       $errors[] = 'Full name is required.';
        if (strlen($username) < 3)                  $errors[] = 'Username must be at least 3 characters.';
        if (User::usernameTaken($username, $id ?: null)) $errors[] = 'That username is already taken.';
        if (!$id && strlen($password) < 6)          $errors[] = 'Password must be at least 6 characters.';
        if ($password !== '' && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($id === Auth::id() && $status === 'inactive') {
            $errors[] = 'You cannot deactivate the account you are currently signed in with.';
        }
        if ($id === Auth::id() && $role !== 'admin') {
            $errors[] = 'You cannot remove your own administrator role while signed in.';
        }
        // Never leave the shop without a way back in.
        if ($id && ($role !== 'admin' || $status !== 'active')) {
            $before = User::find($id);
            if ($before && $before['role'] === 'admin' && User::countActiveAdmins() <= 1) {
                $errors[] = 'This is the last active administrator account, so its role cannot be changed.';
            }
        }

        if ($errors) {
            flash('danger', '<strong>Account not saved.</strong><br>' . implode('<br>', array_map('e', $errors)));
            redirect('users');
        }

        $payload = [
            'full_name'  => $fullName, 'username' => $username, 'password' => $password,
            'role'       => $role,     'status'   => $status,   'contact_no' => $contact,
        ];

        if ($id) {
            User::update($id, $payload);
            flash('success', 'Account <strong>' . e($username) . '</strong> updated.');
        } else {
            User::create($payload);
            flash('success', 'Account <strong>' . e($username) . '</strong> created.');
        }
        redirect('users');
    }

    public static function delete(): void
    {
        $id = (int) input('id');
        $target = User::find($id);
        if ($target && $target['role'] === 'admin' && $target['status'] === 'active' && User::countActiveAdmins() <= 1) {
            flash('warning', 'The last active administrator cannot be deactivated.');
            redirect('users');
        }
        if ($id === Auth::id()) {
            flash('warning', 'You cannot deactivate your own account while signed in.');
        } else {
            User::delete($id);
            flash('success', 'The account has been deactivated.');
        }
        redirect('users');
    }
}
