<?php

namespace App\Http\Resources\Assessment;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentForGroupResource extends JsonResource
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
            'color' =>  $this->color,
            'condition_min' => $this->condition_min,
            'condition_max' => $this->condition_max,
            'condition_avg' => $this->condition_avg,
            'blues' => $this->blues,
            'tones' => $this->tones,
            'condition_score' => (int)$this->condition_score,
            'date_assessment' => (int)$this->date_assessment,
            'planned_date_harvest' => (int)$this->planned_date_harvest,
            'comment' => !empty($this->comment) ? $this->comment : '',
            'created_at' => Carbon::parse($this->created_at)->timestamp,
            'images' => $this->photos
        ];
    }
}
