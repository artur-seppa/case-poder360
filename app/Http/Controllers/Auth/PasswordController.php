<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\UpdatePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class PasswordController extends Controller
{
    public function __construct(private readonly UpdatePassword $updatePassword)
    {
    }

    /**
     * Update the user's password.
     */
    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $newPassword = $request->validated('password');

        $this->updatePassword->handle($request->user(), $newPassword);

        // Invalidates any other active session for this user (e.g. another
        // browser/device) — the whole point of changing your password after
        // suspecting unauthorized access is defeated if a stolen session
        // cookie elsewhere just keeps working.
        Auth::logoutOtherDevices($newPassword);

        return back()->with('status', 'password-updated');
    }
}
