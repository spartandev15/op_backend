<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Position;
use App\Models\Employee;

class UserController extends Controller
{
    public function getUser(){
        $currentUser = User::where('is_deleted', 0)
                    ->where('id', Auth::user()->id)
                    ->first();
        if($currentUser){
            return response()->json([
                'status' => true,
                'user' => $currentUser,
            ], 200);
        }else{
            return response()->json([
                'status' => false,
                'message' => "User not found",
            ], 404);
        }
    }

    public function updateProfile(Request $request){
        $inputValidation = Validator::make($request->all(), [
            "companyName" => 'required',
            "companyType" => 'required',
            "fullName" => 'required',
            "designation" => 'required',
            "companyPhone" => 'required|regex:/^[0-9]{10}$/',
            "registrationNumber" => 'required',
            "companySocialLink" => $request->companySocialLink ? 'url' : '',
        ]);
        if($inputValidation->fails()){
            return response()->json([
                'message' => 'Invalid data entered',
                'errors' => $inputValidation->errors(),
            ], 422);
        }
        $user = User::find(Auth::user()->id);
        $userUpdated = $user->update([
            "company_name" => $request->companyName,
            "company_type" => $request->companyType,
            "full_name" => $request->fullName,
            "designation" => $request->designation,
            "company_phone" => $request->companyPhone,
            "company_address" => $request->companyAddress ?? null,
            "company_city" => $request->companyCity ?? null,
            "company_state" => $request->companyState ?? null,
            "company_country" => $request->companyCountry ?? null,
            "company_postal_code" => $request->companyPostalCode ?? null,
            "registration_number" => $request->registrationNumber,
            "webmaster_email" => $request->companyWebmasterEmail ?? null,
            "company_social_link" => $request->companySocialLink ?? null,
        ]);
        if( $userUpdated ){
            $updatedUser = User::find($user->id);
            return response()->json([
                'status' => true,
                'message' => "User successfully updated",
                'user' => $updatedUser,
            ], 200);
        }
        return response()->json([
            'message' => 'Some error occured',
        ], 401);
    }

    public function updateUserImage(Request $request){
        $inputValidation = Validator::make($request->all(), [
            "logoImage" => 'required|file|mimes:jpg,jpeg,png|max:2048'
        ]);
        if($inputValidation->fails()){
            return response()->json([
                'message' => 'Upload image of type jpg|jpeg|png and size upto 2MB',
                'errors' => $inputValidation->errors(),
            ], 422);
        }
        $user = User::find(Auth::user()->id);
        $oldLogoImage = $request->input('oldLogoImage', null);
        $image = null;
        if($request->hasFile('logoImage')){
            $randomNumber = random_int(1000, 9999);
            $file = $request->logoImage;
            $date = date('YmdHis');
            $filename = "LOGO_IMG_" . $randomNumber . "_" . $date;
            $extension = strtolower( $file->getClientOriginalExtension() );
            $imageName = $filename . '.' . $extension;
            $uploadPath = "uploads/users/logo_images/";
            $imageUrl = $uploadPath . $imageName;
            $file->move($uploadPath, $imageName);
            $image = $imageUrl;
            if($oldLogoImage != "" && File::exists($oldLogoImage)){
                File::delete($oldLogoImage);
            }
        }else{
            $image = $oldLogoImage;
        }
        $userUpdated = $user->update([
            "image" => $image,
        ]);
        if( $userUpdated ){
            return response()->json([
                'status' => true,
                'message' => "Image updated successfully",
                'newImage' => $image,
            ], 200);
        }
        return response()->json([
            'message' => 'Some error occured',
        ], 401);
    }

    public function updateUserPassword(Request $request){
        $inputValidation = Validator::make($request->all(), [
            "oldPassword" => 'required',
            "newPassword" => 'required|confirmed'
        ]);
        if($inputValidation->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Invalid data entered',
                'errors' => $inputValidation->errors(),
            ], 422);
        }
        if($request->oldPassword == $request->newPassword){
            return response()->json([ 'status' => false, 'message' => "Old and New passwords are same. Please choose different password", ], 422);
        }
        try{
            $user = User::find( Auth::user()->id );
            if(Hash::check($request->oldPassword, $user->password)){
                $user->update([
                    'password' => Hash::make($request->newPassword)
                ]);
                // Get the current token being used for authentication
                $currentToken = Auth::user()->currentAccessToken();
                // Remove all tokens except the current one
                $user->tokens->each(function ($token) use ($currentToken) {
                    if ($token->id !== $currentToken->id) {
                        $token->delete();
                    }
                });
                return response()->json([
                    'status' => true,
                    'message' => "Password updated successfully",
                ], 200);
            }else{
                return response()->json([
                    'status' => false,
                    'message' => "Old Password does not match",
                ], 400);
            }
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => "Some exception occured",
            ], 400);
        }
    }

    public function addPositions(Request $request){
        $inputValidation = Validator::make($request->all(), [
            "positions" => 'required',
        ]);
        if($inputValidation->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Invalid data entered',
                'errors' => $inputValidation->errors(),
            ], 422);
        }
        try {
            $positions = explode(",", $request->positions);
            $count = 0 ;
            $positionAlreadyExist = [];
            foreach($positions as $position) {
                if( !Position::where('position', trim($position))->where('added_by', Auth::user()->id)->exists() ){
                    Position::create([
                        "position" => trim($position),
                        "added_by" => Auth::user()->id,
                    ]);
                    $count++;
                }else{
                    $positionAlreadyExist[] = $position;
                }
            }
            if( count($positionAlreadyExist) ){
                return response()->json([
                    'status' => true,
                    'message' => $count . ($count == 1 ? " Position" : " Positions") . " saved. '" . implode(",", $positionAlreadyExist).  "' already exists.",
                ], 200);    
            }
            return response()->json([
                'status' => true,
                'message' => "Successfully added",
            ], 200);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => "Unable to add position. Please try again.",
            ], 400);
        }
    }

    public function getPositions(){
        $positions = Position::select('id', 'position')
                    ->where('added_by', Auth::user()->id)
                    ->get();
        return response()->json([
            'status' => true,
            'positions' => $positions,
        ], 200);
    }

    public function updatePosition(Request $request, String $id){
        $inputValidation = Validator::make($request->all(), [
            "position" => 'required',
        ]);
        if($inputValidation->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Invalid data entered',
            ], 422);
        }
        $position = Position::where('id', $id)->first();
        if($position){
            if( !Position::where('position', trim($request->position))->where('added_by', Auth::user()->id)->exists() ){
                Employee::where('added_by', Auth::user()->id)
                        ->where('position', $position->position)
                        ->update(['position' => trim($request->position)]);
                $position->update([
                    "position" => trim($request->position)
                ]);
                return response()->json([
                    'status' => true,
                    'message' => "Successfully updated",
                ], 200);
            }else{
                return response()->json([
                    'status' => false,
                    'message' => "Position already exists",
                ], 400);
            }
        
        }else{
            return response()->json([
                'status' => false,
                'message' => "Unable to update position",
            ], 400);
        }


    }

    public function removePosition(String $id){
        $positionDeleted = Position::where('id', $id)->delete();
        if($positionDeleted){
            return response()->json([
                'status' => true,
                'message' => "successfully deleted",
            ], 200);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Some error occured",
            ], 400);
        }
    }

    public function getPositionAlreadyInUse(String $position){
        $count = Employee::where('added_by', Auth::user()->id)
                ->where('is_deleted', 0)
                ->where('position', $position)
                ->count();
        return response()->json([
            'status' => true,
            'positionInUse' => $count,
        ], 200);
    }

    public function getCompanies(){
        $allCompanies = User::select(
                    DB::raw('CONCAT(LCASE(REPLACE(company_name, " ", "")),"_", id) AS sid'),
                    'company_name',
                    'company_type',
                    'full_name',
                    'designation',
                    'domain_name',
                    'email',
                    'image',
                    'email_verified',
                    'email_verified_at',
                    'remember_token',
                    'created_at',
                    'updated_at',
                    'role',
                    'coupon',
                    'terms_and_conditions',
                    'is_deleted',
                    'deleted_by',
                    'company_phone',
                    'webmaster_email',
                    'company_address',
                    'company_city',
                    'company_state',
                    'company_country',
                    'company_postal_code',
                    'registration_number',
                    'company_social_link',
                    'is_account_verified',
                    'taken_membership',
                )
                ->where('is_deleted', 0)->where('is_account_verified', 1)->paginate(10);
        if($allCompanies){
            return response()->json([
                'status' => true,
                'allCompanies' => $allCompanies,
            ], 200);
        }else{
            return response()->json([ 'status' => false, 'message' => "No record Found", ], 404);
        }
    }

    public function getCompanyById(String $id){
        $company = User::select(
                        DB::raw('CONCAT(LCASE(REPLACE(company_name, " ", "")),"_", id) AS sid'),
                        'company_name',
                        'company_type',
                        'full_name',
                        'designation',
                        'domain_name',
                        'email',
                        'image',
                        'email_verified',
                        'email_verified_at',
                        'remember_token',
                        'created_at',
                        'updated_at',
                        'role',
                        'coupon',
                        'terms_and_conditions',
                        'is_deleted',
                        'deleted_by',
                        'company_phone',
                        'webmaster_email',
                        'company_address',
                        'company_city',
                        'company_state',
                        'company_country',
                        'company_postal_code',
                        'registration_number',
                        'company_social_link',
                        'is_account_verified',
                        'taken_membership',
                    )
                    ->where('id', $id)
                    ->where('is_deleted', 0)
                    ->first();
        $totalCurrentEmp = Employee::where('added_by', '=', $id)
                    ->where('ex_employee', '=', 0)
                    ->where('non_joiner', '=', 0)
                    ->where('is_deleted', '=', 0)
                    ->count();
        $totalExEmp = Employee::where('added_by', '=', $id)
                    ->where('ex_employee', '=', 1)
                    ->where('non_joiner', '=', 0)
                    ->where('is_deleted', '=', 0)
                    ->count();
        $totalNonJoiner = Employee::where('added_by', '=', $id)
                    ->where('ex_employee', '=', 0)
                    ->where('non_joiner', '=', 1)
                    ->where('is_deleted', '=', 0)
                    ->count();
        $totalSubmittedReview = $totalExEmp + $totalNonJoiner;
        if($company){
            return response()->json([
                'status' => true,
                'totalCurrentEmp' => $totalCurrentEmp,
                'totalExEmp' => $totalExEmp,
                'totalNonJoiner' => $totalNonJoiner,
                'totalSubmittedReview' => $totalSubmittedReview,
                'company' => $company,
            ], 200);
        }else{
            return response()->json([ 'status' => false, 'message' => "Company not found", ], 404);
        }
    }

    public function deleteCompany(String $id){
        $companydetails = User::find($id);
        if($companydetails){
            try{
                Employee::where('added_by', $id)->update([
                    'is_deleted' => 1,
                ]);
                $companydetails->update([
                    "is_deleted" => 1,
                    "deleted_at" => now(),
                ]);
                return response()->json([ 'status' => true, 'message' => "Successfully deleted", ], 200);
            }catch(\Exception $e){
                return response()->json([ 'status' => false, 'message' => "Some error occured", ], 400);
            }
        }else{
            return response()->json([ 'status' => false, 'message' => "Some error occured", ], 400);
        }
    }

    public function verifyCompany(String $id){
        $companydetails = User::where('id', $id)->where('is_deleted', 0)->first();
        if($companydetails){
            $companydetails->update([
                "is_account_verified" => 1
            ]);
            $useremail = $companydetails->email;
            $data = [
                'CompanyName' => 'ORPECT',
                'websiteLink' => config('app.url'),
                'websiteLogin' => config('app.url').'login',
            ];
            try{ 
                Mail::send('auth.accountVerified', ['data' => $data], function ($message) use ($useremail){
                    $message->from('support@orpect.com', 'ORPECT');
                    $message->to($useremail)->subject('ORPECT - Account Verified'); 
                });
            } catch(\Exception $e){
            }
            return response()->json([
                'status' => true,
                'messsage' => "Company successfully verified",
            ], 200);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Company doesn't exists",
            ], 404);
        }
    }

    public function rejectCompany(String $id){
        $companydetails = User::find($id);
        if($companydetails){
            $useremail = $companydetails->email;
            $companydetails->delete();
            $data = [
                'CompanyName' => 'ORPECT',
            ];
            try{ 
                Mail::send('auth.rejectAccount', ['data' => $data], function ($message) use ($useremail){
                    $message->from('support@orpect.com', 'ORPECT');
                    $message->to($useremail)->subject('ORPECT - Registration Request Declined'); 
                });
            } catch(\Exception $e){
            }
            return response()->json([
                'status' => true,
                'messsage' => "Company registration request declined",
            ], 200);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Company doesn't exists",
            ], 404);
        }
    }

    public function getPendingVerificationRequests(){
        $companydetails = User::select(
                            DB::raw('CONCAT(LCASE(REPLACE(company_name, " ", "")),"_", id) AS sid'),
                            'company_name',
                            'company_type',
                            'full_name',
                            'designation',
                            'domain_name',
                            'email',
                            'company_phone',
                            'registration_number',
                            'created_at AS registration_time',
                        )
            ->where('is_account_verified', 0)
            ->where('is_deleted', 0)
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        return response()->json([
            'status' => true,
            'pendingRequests' => $companydetails,
        ], 200);
    }

    public function getDeletedCompanies(Request $request){
        $searchValue = $request->input('searchText', '');
        $deletedCompanies = User::select(
                                DB::raw('CONCAT(LCASE(REPLACE(company_name, " ", "")),"_", id) AS sid'),
                                'company_name',
                                'company_type',
                                'full_name',
                                'designation',
                                'domain_name',
                                'email',
                                'image',
                                'email_verified',
                                'email_verified_at',
                                'remember_token',
                                DB::raw("DATE_FORMAT(created_at, '%d-%m-%Y') AS comp_created_at"),
                                'is_deleted',
                                'company_phone',
                                'webmaster_email',
                                'company_address',
                                'company_city',
                                'company_state',
                                'company_country',
                                'company_postal_code',
                                'registration_number',
                                'company_social_link',
                                'is_account_verified',
                                'taken_membership',
                            )
                            ->where('is_deleted', 1);
                if (!empty($searchValue)) {
                    $deletedCompanies->where(function ($query) use ($searchValue) {
                        $query->where('company_name', 'LIKE', "%$searchValue%")
                            ->orWhere('email', 'LIKE', "%$searchValue%")
                            ->orWhere('domain_name', 'LIKE', "%$searchValue%");
                    });
                }
        $deletedCompanies = $deletedCompanies->orderBy('deleted_at', 'desc')
                            ->paginate(10);
        if($deletedCompanies){
            return response()->json([
                'status' => true,
                'deletedCompanies' => $deletedCompanies,
            ], 200);
        }else{
            return response()->json([ 'status' => false, 'message' => "No record Found", ], 404);
        }
    }

    public function restoreCompany(String $id){
        $companydetails = User::find($id);
        if($companydetails && $companydetails->is_deleted == 1){
            try{
                DB::beginTransaction();
                Employee::where('added_by', $id)->update([
                    'is_deleted' => 0,
                ]);
                $companydetails->update([
                    "is_deleted" => 0,
                ]);
                DB::commit();
                return response()->json([ 'status' => true, 'message' => "Successfully restored", ], 200);
            }catch(\Exception $e){
                DB::rollback();
                return response()->json([ 'status' => false, 'message' => "Some error occured while restoring", ], 400);
            }
        }else{
            return response()->json([ 'status' => false, 'message' => "Some error occured", ], 400);
        }
    }

    public function permanentlyDeleteCompany(String $id){
        $companydetails = User::find($id);
        if($companydetails){
            try{
                DB::beginTransaction();
                $allEmployees = Employee::where('added_by', $id)->get(['id', 'profile_image']);

                // Delete the images from the database and file storage
                foreach ($allEmployees as $employee) {
                    if ($employee->profile_image) {
                        // Check if the file exists before attempting to delete it
                        if(File::exists($employee->profile_image)){
                            File::delete($employee->profile_image);
                        }

                        // Update the image field in the database to null
                        Employee::where('id', $employee->id)->update(['profile_image' => null]);
                    }
                }
                // Delete all employees from the database
                Employee::where('added_by', $id)->delete();

                if ($companydetails->image) {
                    // Check if the file exists before attempting to delete it
                    if(File::exists($companydetails->image)){
                        File::delete($companydetails->image);
                    }

                    // Update the image field in the database to null
                    User::where('id', $companydetails->id)->update(['image' => null]);
                }
                $companydetails->delete();
                Position::where("added_by", $id)->delete();
                DB::commit();
                return response()->json([ 'status' => true, 'message' => "Successfully deleted", ], 200);
            }catch(\Exception $e){
                DB::rollback();
                return response()->json([ 'status' => false, 'message' => "Some error occured while deleting", ], 400);
            }
        }else{
            return response()->json([ 'status' => false, 'message' => "Some error occured", ], 400);
        }
    }
}
