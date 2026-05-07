<?php

namespace App\Http\Controllers;

use App\Jobs\PublishUserEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(User::paginate(15));
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Publish event to RabbitMQ → Notification Service will pick it up
        PublishUserEvent::dispatch([
            'event'   => 'user.registered',
            'user_id' => $user->id,
            'name'    => $user->name,
            'email'   => $user->email,
        ]);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        // Publish update event
        PublishUserEvent::dispatch([
            'event'   => 'user.updated',
            'user_id' => $user->id,
            'changes' => $validated,
        ]);

        return response()->json($user);
    }

    public function destroy(User $user): JsonResponse
    {
        PublishUserEvent::dispatch([
            'event'   => 'user.deleted',
            'user_id' => $user->id,
            'email'   => $user->email,
        ]);

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}
