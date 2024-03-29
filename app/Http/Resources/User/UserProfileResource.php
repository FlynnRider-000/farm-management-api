<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\GetPermissionsResource;
use App\Http\Resources\User\EmailChangeRequestDataResource;
use Illuminate\Support\Facades\Storage;

class UserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'role' => $request->input('account_id') ? $this->user->getAccount($request->input('account_id'))->pivot->roles[0]['name'] : [],
            'permissions' => $request->input('account_id') ? GetPermissionsResource::collection($this->user->getAccount($request->input('account_id'))->pivot->permissions) : [],
            'phone_number' => $this->phone_number,
            'company_name' => $this->company_name,
            'company_address' => $this->company_address,
            'avatar' => !empty($this->avatar) ? url('/', [], true) . $this->avatar : null,
            'change_email_data' => new EmailChangeRequestDataResource($this->user->changeEmails)
        ];
    }
}
