<?php

namespace App\Http\Controllers;

use App\Models\Chirp;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChirpController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User|null $user */
        $user = $request->user();
        $guestLikeId = $user ? null : $this->guestLikeId($request);

        $chirps = Chirp::query()
            ->with('user')
            ->withCount('likes')
            ->with([
                'likes' => function ($query) use ($guestLikeId, $user): void {
                    if ($user) {
                        $query->where('user_id', $user->id);

                        return;
                    }

                    $query->where('session_id', $guestLikeId);
                },
            ])
            ->latest()
            ->take(50)
            ->get();

        return view('home', ['chirps' => $chirps]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:255',
        ]);

        /** @var User $user */
        $user = $request->user();

        $user->chirps()->create($validated);

        return redirect('/')->with('success', 'Your chirp has been posted!');
    }

    public function edit(Chirp $chirp): View
    {
        $this->authorize('update', $chirp);

        return view('edit', compact('chirp'));
    }

    public function update(Request $request, Chirp $chirp): RedirectResponse
    {
        $this->authorize('update', $chirp);

        $validated = $request->validate([
            'message' => 'required|string|max:255',
        ]);

        $chirp->update($validated);

        return redirect('/')->with('success', 'Chirp updated!');
    }

    public function destroy(Chirp $chirp): RedirectResponse
    {
        $this->authorize('delete', $chirp);

        $chirp->delete();

        return redirect('/')->with('success', 'Chirp deleted!');
    }

    protected function guestLikeId(Request $request): string
    {
        return (string) $request->session()->remember('guest_like_id', fn (): string => (string) Str::uuid());
    }
}
