<?php

use App\Jobs\SendPasswordResetEmail;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\LogicException as MailerLogicException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Exception\RfcComplianceException;

it('retries up to 3 times with backoff', function () {
    $job = new SendPasswordResetEmail(User::factory()->make(), 'a-token');

    expect($job->tries)->toBe(3);
    expect($job->backoff())->toBe([10, 30, 60]);
});

it('is dispatched when a password reset is requested', function () {
    Bus::fake();

    $user = User::factory()->create();

    $user->sendPasswordResetNotification('a-token');

    Bus::assertDispatched(
        SendPasswordResetEmail::class,
        fn (SendPasswordResetEmail $job) => $job->user->is($user) && $job->token === 'a-token',
    );
});

it('sends the notification synchronously when handled', function () {
    Notification::fake();

    $user = User::factory()->create();

    (new SendPasswordResetEmail($user, 'a-token'))->handle();

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        fn ($notification) => $notification->token === 'a-token',
    );
});

it('fails without retrying on a malformed recipient address', function () {
    $user = Mockery::mock(User::factory()->create())->makePartial();
    $user->shouldReceive('notifyNow')->once()->andThrow(new RfcComplianceException('invalid address'));

    $job = new SendPasswordResetEmail($user, 'a-token');

    expect(fn () => $job->handle())->not->toThrow(Throwable::class);
});

it('fails without retrying on a mailer logic error', function () {
    $user = Mockery::mock(User::factory()->create())->makePartial();
    $user->shouldReceive('notifyNow')->once()->andThrow(new MailerLogicException('bad message'));

    $job = new SendPasswordResetEmail($user, 'a-token');

    expect(fn () => $job->handle())->not->toThrow(Throwable::class);
});

it('lets a transient transport failure propagate so the worker retries it', function () {
    $user = Mockery::mock(User::factory()->create())->makePartial();
    $user->shouldReceive('notifyNow')->once()->andThrow(new TransportException('smtp unavailable'));

    $job = new SendPasswordResetEmail($user, 'a-token');

    expect(fn () => $job->handle())->toThrow(TransportException::class);
});
