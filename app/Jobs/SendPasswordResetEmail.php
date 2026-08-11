<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mailer\Exception\LogicException as MailerLogicException;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Throwable;

class SendPasswordResetEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly User $user,
        public readonly string $token,
    ) {
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Sends synchronously (notifyNow) because this job is already the queued
     * unit of work — queueing the notification too would just double-queue it.
     * The try/catch lets us tell a malformed message (deterministic, "422",
     * retrying won't help) from a transient SMTP failure (worth retrying,
     * "500") — see the `dontRetry`-doesn't-apply-here note below.
     */
    public function handle(): void
    {
        try {
            $this->user->notifyNow(new ResetPasswordNotification($this->token));
        } catch (RfcComplianceException|MailerLogicException $e) {
            // We'd normally register these in `Handler::dontRetry()` (bootstrap/app.php)
            // instead of catching them here, but `nunomaduro/collision` replaces the
            // bound ExceptionHandlerContract for any console command (including
            // `artisan horizon`) with one that doesn't implement `shouldStopRetries()`,
            // so the Worker silently skips that check. Failing explicitly here works
            // regardless of which exception handler is bound.
            $this->fail($e);
        }
    }
}
