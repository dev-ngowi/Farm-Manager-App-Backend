<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::orderBy('name')->get();

        return response()->json([
            'data' => $roles,
            'message' => 'success',
        ], 200);
    }
}
