<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rules;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    // Define the roles that must strictly use email for login
    private $strict_roles = ['Vet', 'Researcher', 'Admin'];

    /**
     * Generates a unique username based on the user's last name and phone number.
     *
     * @param string $lastname
     * @param string $phone
     * @return string
     */
    private function generateUsername(string $lastname, string $phone): string
    {
        $cleanLastname = strtolower(preg_replace('/[^a-z0-9]/i', '', $lastname));
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        $base = $cleanLastname . $cleanPhone;
        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }

    // ---
    // VALIDATION RULES
    // ---
    /**
     * Get the validation rules for user creation or update.
     *
     * @param Request $request
     * @param User|null $user
     * @return array
     */
    private function getValidationRules(Request $request, ?User $user = null): array
    {
        $is_update = !is_null($user);
        $role_name = $request->input('role');
        if ($is_update && !$role_name) {
            $role_name = $user->getRoleNames()->first();
        }
        $is_strict_role = in_array($role_name, $this->strict_roles);

        $email_rules = ['string', 'lowercase', 'email', 'max:255'];
        if ($is_strict_role || ($is_update && $user?->hasAnyRole($this->strict_roles))) {
            $email_rules[] = 'required';
        } else {
            $email_rules[] = 'nullable';
        }
        $email_rules[] = Rule::unique(User::class)->ignore($user?->id);

        $phone_rules = ['string', 'max:20'];
        if ($is_strict_role || ($is_update && $user?->hasAnyRole($this->strict_roles))) {
            $phone_rules[] = 'required';
        } else {
            $phone_rules[] = 'nullable';
        }
        $phone_rules[] = Rule::unique(User::class, 'phone_number')->ignore($user?->id);

        $rules = [
            'firstname' => 'required|string|max:100',
            'lastname' => 'required|string|max:100',
            'role' => $is_update ? 'nullable' : 'nullable',
            'role.*' => 'string|exists:roles,name',
            'email' => $email_rules,
            'phone_number' => $phone_rules,
            'password' => $is_update
                ? ['nullable', 'confirmed', Rules\Password::defaults()]
                : ['required', 'confirmed', Rules\Password::defaults()],
        ];

        return $rules;
    }

    // ---
    // FORMAT USER RESPONSE
    // ---
    /**
     * Formats the user data for API responses.
     *
     * @param User $user
     * @param bool $includeToken
     * @return array
     */
    private function formatUserData(User $user, bool $includeToken = false): array
    {
        // Ensure roles relationship is loaded
        if (!$user->relationLoaded('roles')) {
            $user->load('roles');
        }

        // Get the role name reliably, default to 'unassigned'
        $roleName = $user->getRoleNames()->first() ?? 'unassigned';

        // Get profile status using helper
        $status = $this->getUserProfileStatus($user);

        Log::info('formatUserData', [
            'user_id' => $user->id,
            'role_name' => $roleName,
            'has_location' => $status['has_location'],
            'has_completed_details' => $status['has_completed_details'],
        ]);

        $data = [
            'id'                    => $user->id,
            'firstname'             => $user->firstname,
            'lastname'              => $user->lastname,
            'full_name'             => $user->full_name,
            'phone_number'          => $user->phone_number,
            'email'                 => $user->email,
            'role'                  => $roleName,
            'has_completed_details' => $status['has_completed_details'],
            'has_location'          => $status['has_location'],
            'primary_location_id'   => $status['primary_location_id'],
            'created_at'            => $user->created_at?->toDateTimeString(),
            'updated_at'            => $user->updated_at?->toDateTimeString(),
        ];

        if ($includeToken) {
            $data['access_token'] = $user->currentAccessToken()?->plainTextToken
                ?? $user->createToken('auth_token')->plainTextToken;
        }

        return $data;
    }

    /**
     * Get user's profile completion status
     *
     * @param User $user
     * @return array
     */
    private function getUserProfileStatus(User $user): array
    {
        $role = $user->getRoleNames()->first() ?? 'unassigned';

        $hasCompletedDetails = false;
        $primaryLocationId   = null;

        // Load missing relationships only when needed
        if (!$user->relationLoaded('farmer') && $role === 'Farmer') {
            $user->load('farmer');
        }
        if (!$user->relationLoaded('veterinarian') && $role === 'Vet') {
            $user->load('veterinarian');
        }
        if (!$user->relationLoaded('researcher') && $role === 'Researcher') {
            $user->load('researcher');
        }

        // Always load primary location from the user → works for ALL roles
        if (!$user->relationLoaded('locations')) {
            $user->load(['locations' => fn($q) => $q->wherePivot('is_primary', true)]);
        }

        switch ($role) {
            case 'Farmer':
                $hasCompletedDetails = $user->farmer !== null;
                $primaryLocationId   = $user->farmer?->location_id;
                break;

            case 'Vet':
                $hasCompletedDetails = $user->veterinarian !== null;
                $primaryLocationId   = $user->veterinarian?->location_id;
                break;

            case 'Researcher':
                $hasCompletedDetails = $user->researcher !== null;
                break;

            default:
                $hasCompletedDetails = true;
                break;
        }

        // Primary location comes from the pivot for researchers (and fallback for everyone)
        $primaryLocation = $user->locations->first();
        $primaryLocationId = $primaryLocationId ?? $primaryLocation?->id;

        return [
            'has_location'          => $primaryLocationId !== null,
            'has_location'          => (bool) $primaryLocation,
            'primary_location_id'   => $primaryLocationId,
            'has_completed_details' => $hasCompletedDetails,
        ];
    }

    // ---
    // REGISTER
    // ---
    public function store(Request $request): JsonResponse
    {
        try {
            Log::info('Registration attempt started.', [
                'request_data' => $request->except(['password', 'password_confirmation']),
                'ip'           => $request->ip(),
            ]);

            $request->validate($this->getValidationRules($request));

            $phone_number = $request->phone_number;
            $username = $this->generateUsername($request->lastname, $phone_number);

            $userData = [
                'firstname'    => $request->firstname,
                'lastname'     => $request->lastname,
                'username'     => $username,
                'phone_number' => $phone_number,
                'password'     => Hash::make($request->password),
                'is_active'    => true,
                'last_login'   => now(),
            ];

            if ($request->filled('email')) {
                $userData['email'] = $request->email;
            }

            // --- Start Database Operations in a Transaction ---
            DB::beginTransaction();

            $user = User::create($userData);

            // CRITICAL FIX: Assign 'unassigned' role
            $unassignedRole = Role::where('name', 'unassigned')->first();
            if ($unassignedRole) {
                $user->syncRoles([$unassignedRole]);
                Log::info('Assigned "unassigned" role during registration.', ['user_id' => $user->id]);
            } else {
                Log::warning('The "unassigned" role does not exist in the roles table. Ensure it is seeded.');
            }

            DB::commit();

            event(new Registered($user));
            $token = $user->createToken('auth_token')->plainTextToken;


            return response()->json([
                'status'  => 'success',
                'code'    => Response::HTTP_CREATED,
                'message' => 'User registered successfully. Proceed to login.',
                'data'    => [
                    // Ensure the user is refreshed with the role loaded
                    'user'         => $this->formatUserData($user->fresh()->load('roles')),
                    'access_token' => $token,
                    'token_type'   => 'Bearer',
                ],
            ], Response::HTTP_CREATED);
        } catch (ValidationException $e) {
            DB::rollBack();
            Log::warning('Registration validation failed.', [
                'errors'  => $e->errors(),
                'request' => $request->except(['password', 'password_confirmation'])
            ]);

            return response()->json([
                'status'  => 'error',
                'code'    => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'Validation failed. Please check the fields.',
                'errors'  => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Registration failed with critical server error: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'request' => $request->except(['password', 'password_confirmation'])
            ]);

            return response()->json([
                'status'  => 'error',
                'code'    => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Registration failed due to a server error. Please try again.',
                'debug'   => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function assignRole(Request $request)
    {
        $user = $request->user();
        $requestedRole = $request->input('role');

        // === VALIDATION ===
        $request->validate([
            'role' => ['required', 'string', Rule::in(['Farmer', 'Vet', 'Researcher', 'Admin'])],
        ]);

        Log::info('Role Assignment Started', [
            'user_id'        => $user->id,
            'requested_role' => $requestedRole,
        ]);

        // === CHECK IF USER ALREADY HAS A REAL ROLE ===
        $currentRoleName = $user->getRoleNames()->first();

        if ($currentRoleName && $currentRoleName !== 'unassigned') {
            Log::info('User already has role', [
                'user_id' => $user->id,
                'current_role' => $currentRoleName,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Tayari umechagua jukumu la ' . $this->swahiliRole($currentRoleName) . '. Huwezi kubadilisha sasa.',
                'user'    => $this->formatUserData($user, true),
            ], 200);
        }

        // === ASSIGN THE ROLE ===
        DB::beginTransaction();

        try {
            $role = Role::where('name', $requestedRole)->firstOrFail();

            // Sync the new role (this removes 'unassigned')
            $user->syncRoles([$role]);

            DB::commit();

            // Reload user with fresh roles relationship
            $user->load('roles');

            Log::info('Role Assignment SUCCESS', [
                'user_id'       => $user->id,
                'assigned_role' => $requestedRole,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Hongera! Sasa wewe ni ' . $this->swahiliRole($requestedRole) . ' 🎉',
                'user'    => $this->formatUserData($user, true),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Role Assignment FAILED', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Imeshindwa kuweka jukumu. Tafadhali jaribu tena baadaye.',
            ], 500);
        }
    }

    // Beautiful Swahili role names
    private function swahiliRole($role)
    {
        return match ($role) {
            'Farmer'      => 'Mkulima',
            'Vet'         => 'Daktari wa Mifugo',
            'Researcher'  => 'Mtafiti',
            'Admin'       => 'Msimamizi',
            'unassigned'  => 'Hujachagua Jukumu',
            default       => $role
        };
    }

    // ---
    // R - READ (List Users + Token)
    // ---
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

    // ---
    // R - READ (Single User)
    // ---
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

    // ---
    // U - UPDATE
    // ---
    public function update(Request $request, User $user): JsonResponse
    {
        try {
            $user->load('roles:id,name');
            $request->validate($this->getValidationRules($request, $user));

            $data = $request->only(['firstname', 'lastname', 'phone_number', 'email', 'is_active']);

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $role_name = $request->input('role') ?? $user->getRoleNames()->first();
            $is_strict_role = in_array($role_name, $this->strict_roles);

            // Conditional clearing of fields for non-strict roles (Farmers)
            if (!$is_strict_role) {
                // If a Farmer is being updated and 'email' is explicitly null in the request, set it to null
                if ($request->has('email') && $request->input('email') === null) $data['email'] = null;
                // If a Farmer is being updated and 'phone_number' is explicitly null in the request, set it to null
                if ($request->has('phone_number') && $request->input('phone_number') === null) $data['phone_number'] = null;
            }

            $user->update($data);

            if ($request->filled('role')) {
                $new_role = Role::where('name', $request->role)->first();
                if ($new_role && !$user->hasRole($new_role->name)) {
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

    // ---
    // D - DELETE
    // ---
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


    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'login'    => 'required|string',
                'password' => 'required|string',
            ]);

            $loginInput = $request->login;

            $user = User::with([
                'roles',
                'farmer.location',                    // Farmer has a direct location_id → OK
                'veterinarian.location',              // Vet has a direct location_id → OK
                'researcher',                         // Just load the researcher record
                'locations' => fn($q) => $q->wherePivot('is_primary', true) // Load primary location for EVERYONE (including Researchers)
            ])
                ->where(function ($query) use ($loginInput) {
                    $query->where('phone_number', $loginInput)
                        ->orWhere('email', $loginInput)
                        ->orWhere('username', $loginInput);
                })
                ->first();

            // Authentication Failure
            if (!$user || !Hash::check($request->password, $user->password)) {
                Log::warning('Login failed: Invalid credentials', [
                    'login_input' => $loginInput,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'status'  => 'error',
                    'code'    => Response::HTTP_UNAUTHORIZED,
                    'message' => 'Invalid credentials.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Account Status Check
            if (!$user->is_active) {
                Log::warning('Login blocked: Account deactivated', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                return response()->json([
                    'status'  => 'error',
                    'code'    => Response::HTTP_FORBIDDEN,
                    'message' => 'Account deactivated. Contact admin.',
                ], Response::HTTP_FORBIDDEN);
            }

            // Get the role reliably
            $userRole = $user->getRoleNames()->first() ?? 'unassigned';

            // === ROLE-BASED LOGIN ENFORCEMENT ===
            if (in_array($userRole, $this->strict_roles)) {
                $isLoginInputEmail = ($loginInput === $user->email && !is_null($user->email));

                if (!$isLoginInputEmail) {
                    Log::warning('Login blocked: Strict role must use email', [
                        'user_id' => $user->id,
                        'role' => $userRole,
                        'login_input' => $loginInput,
                    ]);

                    return response()->json([
                        'status'  => 'error',
                        'code'    => Response::HTTP_UNAUTHORIZED,
                        'message' => 'Please log in using your email address.',
                    ], Response::HTTP_UNAUTHORIZED);
                }
            }

            // Update last login time
            $user->update(['last_login' => now()]);

            // Generate access token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Get status using the helper function
            $status = $this->getUserProfileStatus($user);

            Log::info('Login successful', [
                'user_id' => $user->id,
                'role' => $userRole,
                'has_location' => $status['has_location'],
                'has_completed_details' => $status['has_completed_details'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'status'  => 'success',
                'code'    => Response::HTTP_OK,
                'message' => 'Login successful.',
                'data'    => [
                    'user' => [
                        'id' => $user->id,
                        'firstname' => $user->firstname,
                        'lastname' => $user->lastname,
                        'email' => $user->email,
                        'phone_number' => $user->phone_number,
                        'username' => $user->username,
                        'is_active' => $user->is_active,
                        'role' => $userRole,
                        'has_location' => $status['has_location'],
                        'primary_location_id' => $status['primary_location_id'],
                        'has_completed_details' => $status['has_completed_details'],
                        'last_login' => $user->last_login,
                    ],
                    'access_token' => $token,
                    'token_type'   => 'Bearer',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'code'    => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'Please enter your phone number or email.',
                'errors'  => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            Log::error('Login error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
                'debug_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'code'    => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Login failed. Try again.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    // ---
    // LOGOUT
    // ---
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

    // ---
    // REQUEST PASSWORD RESET OTP
    // ---
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

    // ---
    // VERIFY OTP & RESET PASSWORD
    // ---
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
