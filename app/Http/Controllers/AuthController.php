<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\User;
use App\Role;
use Validator, DB, Hash, Mail, Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    public function register(Request $request){
        $credentials = request(['name','email','password','repeat_password']);

        if($credentials['password']!=$credentials['repeat_password']){
            return response()->json([
                'message' => 'Passwords does not match!',
            ], 400);
        }

        $rules = [
            'name' => 'required|max:32|unique:users',
            'email' => 'required|max:255|unique:users',
            'password' => 'required|min:6'
        ];

        $validator = Validator::make($credentials, $rules);
        
        if($validator->fails()){
            return response()->json([
                'message' => 'Errors in your credentials',
                'errors' => $validator->messages()
            ], 400);
        }

        $role = Role::where('name', 'User')->first();
        $name = $credentials['name'];
        $email = $credentials['email'];
        $password = Hash::make($credentials['password']);
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
        $user->roles()->attach($role);


        return $this->login($request);

    }

    public function recoverPassword(Request $request){
        $user = User::where('email', $request->email)->first();

        if(!$user){
            return response()->json([
                'message' => 'Neither user does not match with this email',
            ], 404);
        }

        try {
            Password::sendResetLink($request->only('email'), function (Message $message) {
                $message->subject('Your Password Reset Link');
            });
        } catch (\Exception $e) {
            //Return with error
            $error_message = $e->getMessage();
            return response()->json([
                'message' => $error_message,
            ], 404);
        }
        return response()->json([
            'message' => 'Email has been sent!',
        ], 404);
    }


    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['email', 'password']);

        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }
}