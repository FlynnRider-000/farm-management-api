<?php

namespace App\Repositories\Harvest;

use App\Models\ChartData;
use App\Models\HarvestGroup;
use App\Models\Line;
use App\Models\Task;
use App\Models\Automation;
use App\Models\LineBudget;
use App\Traits\CheckDatesHarvestsTrait;
use Carbon\Carbon;

class HarvestRepository implements HarvestRepositoryInterface
{
    use CheckDatesHarvestsTrait;

    public function startHarvest($attr)
    {
        if($this->checkStartSeeding($attr['line_id'], $attr['planned_date'], $attr['planned_date_harvest'])) {

            HarvestGroup::create([
                'line_id' => $attr['line_id'],
                'name' => $attr['name'],
                'planned_date_harvest' => $attr['planned_date_harvest'],
                'planned_date_harvest_original' => $attr['planned_date_harvest'],
                'planned_date' => $attr['planned_date'],
                'planned_date_original' => $attr['planned_date'],
                'seed_id' => $attr['seed_id'],
                'density' => $attr['density'],
                'drop' => $attr['drop'],
                'floats' => $attr['floats'],
                'spacing' => $attr['spacing'],
                'submersion' => $attr['submersion'],
            ]);

            $automations = Automation::where([
                'creator_id' => auth()->user()->id,
            ])->get();

            $currentLine = Line::find($attr['line_id']);
            foreach($automations as $automation) {
                $type = $automation->action;
                $due_date = 0;
                if ($automation->condition == 'Seeding') {
                    if ($type == 'Created') {
                        $due_date = Carbon::now()->addDays($automation->time)->timestamp * 1000;
                    } else if ($type == 'Completed' || $type == 'Upcoming') {
                        $due_date = Carbon::createFromTimestamp($attr['planned_date'])->addDays($automation->time)->timestamp * 1000;
                    }
                } else if ($automation->condition == 'Harvesting') {
                    if ($type == 'Created') {
                        $due_date = Carbon::now()->addDays($automation->time)->timestamp * 1000;
                    } else if ($type == 'Upcoming') {
                        $due_date = Carbon::createFromTimestamp($attr['planned_date_harvest'])->addDays($automation->time)->timestamp * 1000;
                    }
                }

                if (
                    $automation->condition == 'Seeding' || (
                    $automation->condition == 'Harvesting' && (
                        $type == 'Created' || $type == 'Upcoming'
                    ))
                ) {
                    $task = Task::create([
                        'creator_id' => auth()->user()->id,
                        'farm_id' => $currentLine->farm_id,
                        'title' => $automation->title,
                        'content' => $automation->description,
                        'charger_id' => 0,
                        'line_id' => $attr['line_id'],
                        'due_date' => $due_date,
                    ]);
                }
            }
            return response()->json(['status' => 'Success', 'ab' => $automations], 200);

        } else {

            return response()->json(['status' => 'Error',
                                     'message' => 'The specified harvest period already exists'], 400);

        }
    }

    public function updateHarvest($attr)
    {
        HarvestGroup::where('id', $attr['harvest_group_id'])
            ->update([
                'name' => $attr['name'],
                'planned_date' => $attr['planned_date'],
                'seed_id' => $attr['seed_id'],
                'planned_date_harvest' => $attr['planned_date_harvest'],
                'planned_date_harvest_original' => $attr['planned_date_harvest'],
                'seed_id' => $attr['seed_id'],
                'density' => $attr['density'],
                'drop' => $attr['drop'],
                'floats' => $attr['floats'],
                'spacing' => $attr['spacing'],
                'submersion' => $attr['submersion'],
            ]);

        return response()->json(['status' => 'Success'], 200);
    }
}
