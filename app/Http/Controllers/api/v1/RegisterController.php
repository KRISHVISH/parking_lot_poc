<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator as FacadesValidator;
use Illuminate\Validation\Rule as ValidationRule;

class RegisterController extends Controller
{
    /**
     * Register a user
     *
     * @param Request $request
     * @return Illuminate\Support\Facades\Response
     */
    public function register(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = FacadesValidator::make($request->all(), [
                'name' => 'required|string',
                'email' => 'required|email|unique:users',
                'password' => 'required|min:8'
            ]);

            if ($validator->fails()) {
                return response(['errors' => $validator->errors()->all()]);
            } else {
                try {
                    $user_save = User::create([
                        'name' => $request->name,
                        'email' => $request->email,
                        'password' => bcrypt($request->password)
                    ]);
                } catch (\Exception $e) {
                    DB::rollback();
                    $message = $e->getMessage();
                    return response()->json(['error' => 1, 'message' => $message]);
                }
            }
            DB::commit();
            if($user_save){
                return response()->json([
                    'message' => 'Successfully created user!'
                ], 201);
            }
           
        } catch (\Exception $e) {
            DB::rollback();
            // something went wrong
            $message = $e->getMessage();
            return response()->json(['error' => 1, 'message' => $message]);
        }
    }
}
