<?php

use App\Models\Chirp;
use App\Models\ChirpLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

function createChirpForLikeTest(string $message = 'Test chirp', ?User $user = null, ?string $createdAt = null): Chirp
{
    $user ??= User::factory()->create();

    $chirp = $user->chirps()->create([
        'message' => $message,
    ]);

    if ($createdAt) {
        $timestamp = Carbon::parse($createdAt);
        $chirp->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->saveQuietly();
    }

    return $chirp->fresh();
}

it('allows an authenticated user to like a chirp', function () {
    $user = User::factory()->create();
    $chirp = createChirpForLikeTest();

    $this->actingAs($user)
        ->post(route('chirps.likes.store', $chirp))
        ->assertRedirect('/');

    expect(ChirpLike::query()->where('chirp_id', $chirp->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

it('allows an authenticated user to unlike a chirp', function () {
    $user = User::factory()->create();
    $chirp = createChirpForLikeTest();

    $chirp->likes()->create([
        'user_id' => $user->id,
        'session_id' => null,
    ]);

    $this->actingAs($user)
        ->delete(route('chirps.likes.destroy', $chirp))
        ->assertRedirect('/');

    expect(ChirpLike::query()->where('chirp_id', $chirp->id)->where('user_id', $user->id)->exists())->toBeFalse();
});

it('does not duplicate likes for an authenticated user', function () {
    $user = User::factory()->create();
    $chirp = createChirpForLikeTest();

    $this->actingAs($user)->post(route('chirps.likes.store', $chirp));
    $this->actingAs($user)->post(route('chirps.likes.store', $chirp));

    expect(ChirpLike::query()->where('chirp_id', $chirp->id)->where('user_id', $user->id)->count())->toBe(1);
});

it('allows a guest with a session to like a chirp', function () {
    $chirp = createChirpForLikeTest();

    $this->post(route('chirps.likes.store', $chirp))
        ->assertRedirect('/');

    $sessionId = session('guest_like_id');

    expect(ChirpLike::query()->where('chirp_id', $chirp->id)->where('session_id', $sessionId)->exists())->toBeTrue();
});

it('allows a guest with the same session to unlike a chirp', function () {
    $chirp = createChirpForLikeTest();

    $this->post(route('chirps.likes.store', $chirp));
    $sessionId = session('guest_like_id');

    $this->delete(route('chirps.likes.destroy', $chirp))
        ->assertRedirect('/');

    expect(ChirpLike::query()->where('chirp_id', $chirp->id)->where('session_id', $sessionId)->exists())->toBeFalse();
});

it('does not duplicate likes for a guest session', function () {
    $chirp = createChirpForLikeTest();

    $this->post(route('chirps.likes.store', $chirp));
    $sessionId = session('guest_like_id');
    $this->post(route('chirps.likes.store', $chirp));

    expect(ChirpLike::query()->where('chirp_id', $chirp->id)->where('session_id', $sessionId)->count())->toBe(1);
});

it('counts likes from authenticated users and guests together', function () {
    $chirp = createChirpForLikeTest();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('chirps.likes.store', $chirp));
    Auth::guard()->logout();
    $this->post(route('chirps.likes.store', $chirp));

    expect($chirp->fresh()->likes()->count())->toBe(2);
});

it('shows the feed with like counts', function () {
    $chirp = createChirpForLikeTest('Feed chirp');

    $chirp->likes()->create([
        'session_id' => 'feed-session',
        'user_id' => null,
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Feed chirp')
        ->assertSee('1 like');
});

it('keeps the feed in chronological order after likes are added', function () {
    $olderChirp = createChirpForLikeTest('Older but liked', createdAt: '2026-04-10 10:00:00');
    $newerChirp = createChirpForLikeTest('Newest first', createdAt: '2026-04-10 11:00:00');

    $olderChirp->likes()->create([
        'session_id' => 'session-one',
        'user_id' => null,
    ]);

    $olderChirp->likes()->create([
        'session_id' => 'session-two',
        'user_id' => null,
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSeeInOrder(['Newest first', 'Older but liked']);
});
