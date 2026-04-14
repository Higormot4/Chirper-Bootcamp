<?php

namespace App\Http\Controllers;

use App\Models\Chirp;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChirpLikeController extends Controller
{
    public function store(Request $request, Chirp $chirp): RedirectResponse
    {
        $chirp->likes()->firstOrCreate($this->likeAttributes($request));

        return redirect('/')->with('success', 'Chirp liked!');
    }

    public function destroy(Request $request, Chirp $chirp): RedirectResponse
    {
        $chirp->likes()
            ->where($this->identityColumn($request), $this->identityValue($request))
            ->delete();

        return redirect('/')->with('success', 'Like removed.');
    }

    /**
     * @return array{user_id:int|null, session_id:string|null}
     */
    protected function likeAttributes(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            return [
                'user_id' => $user->id,
                'session_id' => null,
            ];
        }

        return [
            'user_id' => null,
            'session_id' => $this->guestLikeId($request),
        ];
    }

    protected function identityColumn(Request $request): string
    {
        return $request->user() ? 'user_id' : 'session_id';
    }

    protected function identityValue(Request $request): int|string
    {
        /** @var User|null $user */
        $user = $request->user();

        return $user?->id ?? $this->guestLikeId($request);
    }

    protected function guestLikeId(Request $request): string
    {
        return (string) $request->session()->remember('guest_like_id', fn (): string => (string) Str::uuid());
    }
}
