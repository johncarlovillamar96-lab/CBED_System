<?php

use App\Http\Controllers\CooperativeImportController;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('cbed:create-admin {email} {--name=CBED Administrator} {--reset-password}', function () {
    $email = trim($this->argument('email'));
    $name = trim($this->option('name'));
    $user = User::where('email', $email)->first();

    if ($user && (! $user->isAdmin() || ! $this->option('reset-password'))) {
        $this->error('An account already uses this email. Use --reset-password to update an existing admin password.');
        return 1;
    }

    if (! $user && $this->option('reset-password')) {
        $this->error('No admin account was found for this email.');
        return 1;
    }

    $password = $this->secret('Password');
    $validator = Validator::make([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $this->secret('Confirm password'),
    ], [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255'],
        'password' => ['required', 'confirmed', Password::min(User::MIN_PASSWORD_LENGTH)],
    ]);

    if ($validator->fails() || strlen((string) $password) > 72 || $password === 'ChangeMe123!') {
        $this->error($validator->errors()->first() ?: 'Choose a different password of at most 72 bytes.');
        return 1;
    }

    $user ??= new User(['name' => $name, 'email' => $email]);
    $user->role = User::ADMIN;
    $user->password = $password;
    $user->setRememberToken(null);
    $user->save();

    $this->info($this->option('reset-password') ? 'Admin password updated.' : 'Admin account created.');
    return 0;
})->purpose('Create an admin account or reset an existing admin password.');

Artisan::command('cbed:fill-missing-inventory {file} {--cooperative=} {--apply}', function () {
    $path = realpath($this->argument('file'));
    $cooperativeId = (int) $this->option('cooperative');
    if (! $path || ! \Illuminate\Support\Facades\DB::table('cooperatives')->where('id', $cooperativeId)->exists()) {
        $this->error('Provide an existing workbook and --cooperative=<id>.');
        return 1;
    }
    $result = app(CooperativeImportController::class)->fillMissingInventorySheet($path, $cooperativeId, (bool) $this->option('apply'));
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
})->purpose('Preview or fill blank fields on existing animals without inserting records or replacing populated fields.');
