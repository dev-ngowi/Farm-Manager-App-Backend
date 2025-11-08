<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rules;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    private $strict_roles = ['Vet', 'Researcher', 'Admin'];

    /**
     * Helper function to get conditional validation rules.
     */
    private function getValidationRules(Request $request, ?User $user = null): array
    {
        $is_update = !is_null($user);
        $role_name = $request->input('role');
        $is_strict_role = in_array($role_name, $this->strict_roles);

        // --- Email Rules ---
        $email_rules = [
            'string',
            'lowercase',
            'email',
            'max:255',
            Rule::unique(User::class)->ignore($user->id ?? null),
        ];
        if ($is_strict_role) {
            array_unshift($email_rules, 'required');
        } else {
            array_unshift($email_rules, 'nullable');
        }

        if ($is_update && !$role_name) {
            if ($user->hasAnyRole($this->strict_roles)) {
                array_unshift($email_rules, 'required');
            } else {
                array_unshift($email_rules, 'nullable');
            }
        }

        // --- Phone Number Rules ---
        $phone_rules = [
            'string',
            'max:20',
            Rule::unique(User::class, 'phone_number')->ignore($user->id ?? null, 'phone_number'),
        ];

        if ($is_strict_role) {
            array_unshift($phone_rules, 'required');
        } else {
            array_unshift($phone_rules, 'nullable');
        }

        if ($is_update && !$role_name) {
            if ($user->hasAnyRole($this->strict_roles)) {
                array_unshift($phone_rules, 'required');
            } else {
                array_unshift($phone_rules, 'nullable');
            }
        }

        // --- Base Rules ---
        $rules = [
            'firstname' => 'required|string|max:100',
            'lastname' => 'required|string|max:100',
            'role' => 'nullable|string|exists:roles,name',
            'email' => $email_rules,
            'phone_number' => $phone_rules,
            'password' => $is_update
                ? ['nullable', 'confirmed', Rules\Password::defaults()]
                : ['required', 'confirmed', Rules\Password::defaults()],
        ];

        if (!$is_update) {
            $rules['role'] = 'required|string|exists:roles,name';
        }

        return $rules;
    }

    /**
     * Helper function to format user data for response.
     */
    private function formatUserData(User $user, bool $include_timestamps = false): array
    {
        $user_data = $user->only(['id', 'firstname', 'lastname', 'email', 'phone_number', 'is_active']);
        $user_data['role'] = $user->getRoleNames()->first();
        
        if ($include_timestamps) {
            $user_data['created_at'] = $user->created_at;
            $user_data['last_login'] = $user->last_login;
        }

        return $user_data;
    }

    // ==================================
    // C - CREATE (Registration)
    // ==================================
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate($this->getValidationRules($request));

            $email = $request->email ?? null;
            $phone_number = $request->phone_number ?? null;
            $role_name = $request->input('role', 'Farmer');

            $user = User::create([
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'name' => $request->firstname . ' ' . $request->lastname,
                'phone_number' => $phone_number,
                'email' => $email,
                'password' => Hash::make($request->password),
                'is_active' => true,
                'last_login' => now(),
            ]);

            $role = Role::where('name', $role_name)->first();
            if ($role) {
                $user->assignRole($role);
            }

           $userRole = UserRole::create([
                'user_id' => $user->id,
                'role_id' => $role->id
            ]);

            event(new Registered($user));
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'code' => Response::HTTP_CREATED,
                'message' => 'User successfully registered.',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
            ], Response::HTTP_CREATED);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            Log::error('Registration error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->except(['password', 'password_confirmation'])
            ]);
            
            return response()->json([
                'status' => 'error',
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'An error occurred during registration.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ==================================
    // R - READ (List Users + Token)
    // ==================================
    public function index(Request $request): JsonResponse
    {
        try {
            $users = User::with('roles:id,name')->get()->map(function ($user) {
                return $this->formatUserData($user, true);
            });

            $token = $request->user()?->createToken('auth_token')->plainTextToken ?? null;

            return response()->json([
                'status' => 'success',
                'code' => Response::HTTP_OK,
                'message' => 'Users list retrieved successfully.',
                'data' => [
                    'users' => $users,
                    'access_token' => $token,
                ],
            ], Response::HTTP_OK);

        } catch (Exception $e) {
            Log::error('Failed to retrieve users: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Failed to retrieve users.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ==================================
    // R - READ (Single User)
    // ==================================
    public function show(User $user): JsonResponse
    {
        try {
            $user->load('roles:id,name');

            return response()->json([
                'status' => 'success',
                'code' => Response::HTTP_OK,
                'message' => 'User retrieved successfully.',
                'data' => $this->formatUserData($user, true),
            ], Response::HTTP_OK);

        } catch (Exception $e) {
            Log::error('Failed to retrieve user: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null
            ]);
            
            return response()->json([
                'status' => 'error',
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Failed to retrieve user.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ==================================
    // U - UPDATE
    // ==================================
    public function update(Request $request, User $user): JsonResponse
    {
        try {
            $user->load('roles:id,name');
            $request->validate($this->getValidationRules($request, $user));

            $data = $request->only(['firstname', 'lastname', 'phone_number', 'email', 'is_active']);

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            if ($request->filled('firstname') || $request->filled('lastname')) {
                $data['name'] = ($request->firstname ?? $user->firstname) . ' ' . ($request->lastname ?? $user->lastname);
            }

            $role_name = $request->input('role') ?? $user->getRoleNames()->first();
            $is_strict_role = in_array($role_name, $this->strict_roles);

            if (!$is_strict_role) {
                if (!$request->filled('email')) $data['email'] = null;
                if (!$request->filled('phone_number')) $data['phone_number'] = null;
            }

            $user->update($data);

            if ($request->filled('role')) {
                $new_role = Role::where('name', $request->role)->first();
                if ($new_role && !$user->hasRole($new_role)) {
                    $user->syncRoles([$new_role]);
                }
            }

            return response()->json([
                'status' => 'success',
                'code' => Response::HTTP_OK,
                'message' => 'User updated successfully.',
                'data' => $this->formatUserData($user),
            ], Response::HTTP_OK);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            Log::error('Failed to update user: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
                'request' => $request->except(['password', 'password_confirmation'])
            ]);
            
            return response()->json([
                'status' => 'error',
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Failed to update user.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ==================================
    // D - DELETE
    // ==================================
    public function destroy(User $user): JsonResponse
    {
        try {
            $user_id = $user->id;
            $user->delete();

            return response()->json([
                'status' => 'success',
                'code' => Response::HTTP_OK,
                'message' => 'User deleted successfully.',
                'data' => null,
            ], Response::HTTP_OK);

        } catch (Exception $e) {
            Log::error('Failed to delete user: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null
            ]);
            
            return response()->json([
                'status' => 'error',
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Failed to delete user.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ==================================
    // LOGIN
    // ==================================
    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::with('roles:id,name')->where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'code' => Response::HTTP_UNAUTHORIZED,
                    'message' => 'Invalid credentials.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!$user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'code' => Response::HTTP_FORBIDDEN,
                    'message' => 'Account is deactivated.',
                ], Response::HTTP_FORBIDDEN);
            }

            $user->last_login = now();
            $user->save();

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'code' => Response::HTTP_OK,
                'message' => 'Login successful.',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
            ], Response::HTTP_OK);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            Log::error('Login error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'email' => $request->email ?? 'not provided'
            ]);
            
            return response()->json([
                'status' => 'error',
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Login failed.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ==================================
    // LOGOUT
    // ==================================
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'status' => 'success',
                'code' => Response::HTTP_OK,
                'message' => 'Logged out successfully.',
            ], Response::HTTP_OK);

        } catch (Exception $e) {
            Log::error('Logout error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()->id ?? null
            ]);
            
            return response()->json([
                'status' => 'error',
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Logout failed.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ==================================
    // REQUEST PASSWORD RESET OTP
    // ==================================
    public function requestPasswordReset(Request $request): JsonResponse
    {
        try {
            $request->validate(['email' => 'required|email|exists:users,email']);

            $user = User::where('email', $request->email)->first();
            $otp = rand(100000, 999999);

            Cache::put('password_reset_otp_' . $user->id, $otp, now()->addMinutes(10));

            // TODO: Send OTP via email or SMS
            Log::info("Password Reset OTP for {$user->email}: {$otp}");

            return response()->json([
                'status' => 'success',
                'code' => Response::HTTP_OK,
                'message' => 'OTP sent successfully.',
                'data' => ['otp_sent_to' => $user->email],
            ], Response::HTTP_OK);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            Log::error('Failed to send OTP: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'email' => $request->email ?? 'not provided'
            ]);
            
            return response()->json([
                'status' => 'error',
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Failed to send OTP.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ==================================
    // VERIFY OTP & RESET PASSWORD
    // ==================================
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|numeric',
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]);

            $user = User::where('email', $request->email)->first();
            $cachedOtp = Cache::get('password_reset_otp_' . $user->id);

            if (!$cachedOtp || $cachedOtp != $request->otp) {
                return response()->json([
                    'status' => 'error',
                    'code' => Response::HTTP_UNAUTHORIZED,
                    'message' => 'Invalid or expired OTP.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $user->password = Hash::make($request->password);
            $user->save();

            Cache::forget('password_reset_otp_' . $user->id);

            return response()->json([
                'status' => 'success',
                'code' => Response::HTTP_OK,
                'message' => 'Password reset successfully.',
            ], Response::HTTP_OK);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            Log::error('Password reset failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'email' => $request->email ?? 'not provided'
            ]);
            
            return response()->json([
                'status' => 'error',
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Password reset failed.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}