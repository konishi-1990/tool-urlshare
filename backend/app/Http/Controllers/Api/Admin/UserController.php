<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::orderBy('created_at', 'desc')->get(['id', 'email', 'is_admin', 'created_at']);

        return response()->json(['data' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'is_admin' => ['boolean'],
        ]);

        $user = User::create([
            'email'    => $data['email'],
            'password' => $data['password'],
            'is_admin' => $data['is_admin'] ?? false,
        ]);

        return response()->json([
            'id'         => $user->id,
            'email'      => $user->email,
            'is_admin'   => $user->is_admin,
            'created_at' => $user->created_at,
        ], 201);
    }

    public function destroy(Request $request, User $user): Response
    {
        if ($request->user()->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => ['自分自身は削除できません。'],
            ]);
        }

        $user->delete();

        return response()->noContent();
    }
}
