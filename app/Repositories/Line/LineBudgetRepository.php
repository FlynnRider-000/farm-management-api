<?php

namespace App\Repositories\Line;

use App\Http\Resources\Budget\FarmBudgetResource;
use App\Models\Expenses;
use App\Models\FarmExpenses;
use App\Models\Farm;
use App\Models\Line;
use App\Models\SeedingCost;
use App\Models\MaintenanceCost;
use App\Models\LineBudget;
use App\Http\Resources\Budget\BudgetFarmsLinesResourse;
use App\Http\Resources\Budget\BudgetFarmsLinesByPeriodResource;
use App\Repositories\Xero\InvoiceRepository;
use Carbon\Carbon;

class LineBudgetRepository implements LineBudgetRepositoryInterface {

    private $invoiceRepo;

    public function __construct(InvoiceRepository $invoice)
    {
        $this->invoiceRepo = $invoice;
    }

    public function createBudget($attr)
    {
        if($attr['line_id']) {

            $lineBudget = LineBudget::where('line_id', $attr['line_id'])->first();
            $lineBudget->update([
                'planned_harvest_tones' => isset($attr['planned_harvest_tones']) ? $attr['planned_harvest_tones'] : 0,
                'budgeted_harvest_income' => isset($attr['budgeted_harvest_income']) ? $attr['budgeted_harvest_income'] : 0,
//                'length_actual' => isset($attr['length_actual']) ? $attr['length_actual'] : 0,
                'planned_harvest_tones_actual' => isset($attr['planned_harvest_tones_actual']) ? $attr['planned_harvest_tones_actual'] : 0,
                'budgeted_harvest_income_actual' => isset($attr['budgeted_harvest_income_actual']) ? $attr['budgeted_harvest_income_actual'] : 0
            ]);

            if ($lineBudget && isset($attr['expenses'])) {

                foreach($attr['expenses'] as $expens) {
                    if ($expens['type'] == 's') {

                        Expenses::create([
                            'line_budget_id' => $lineBudget->id,
                            'type' => 's',
                            'expenses_name' => $expens['expenses_name'],
                            'price_budget' => $expens['price_budget'],
                            'price_actual' => $expens['price_actual'],
                        ]);

                    } elseif ($expens['type'] == 'm') {
                        Expenses::create([
                            'line_budget_id' => $lineBudget->id,
                            'type' => 'm',
                            'expenses_name' => $expens['expenses_name'],
                            'price_budget' => $expens['price_budget'],
                            'price_actual' => $expens['price_actual'],
                        ]);
                    } else {

                        return response()->json(['status' => 'Error', 'message' => 'Invalid type'], 400);

                    }
                }
            }

            return response()->json(['status' => 'Success'], 200);

        } else {

            return response()->json(['status' => 'Error'], 404);

        }

    }

    public function getUserFarmsBudget()
    {
        $u = auth()->user()->id;

//        $budgets = Farm::whereHas('users', function($q) use ($u) {
//                            $q->where('user_id', '=', $u);
//                        })->with(['lines_budgets' => function($q) {
//                            $q->with(['budgets' => function($r) {
//                                $r->orderBy('start_budget', 'DESC');
//                            }]);}])->get();
        $budgets = Farm::whereHas('users', function($q) use ($u) {
                            $q->where('user_id', '=', $u);
                        })->with('lines', function($q) {
                            $q->with('overview_budgets');
                        })->get();
//->with(['overview_budgets' => function($r) {
//                                $r->orderBy('start_budget', 'DESC');
////                            }]);
//                        }])->get();


        return BudgetFarmsLinesResourse::collection($budgets);
    }

    public function newExpenses($attr)
    {
        foreach($attr['expenses'] as $expens) {

            if($expens['type'] == 's' || $expens['type'] == 'm') {

                Expenses::create([
                    'line_budget_id' => $expens['line_budget_id'],
                    'type' => $expens['type'],
                    'expenses_name' => $expens['expenses_name'],
                    'price_budget' => $expens['price_budget'],
                    'price_actual' => $expens['price_actual'],
                    'expense_date' => $expens['expense_date'],
                    'rdata' => json_encode($expens),
                ]);

                $acc = auth()->user()->getAccount();
                if ($expens['budget_type'] == 'a' && $expens['to_xero'] && $acc->xero_access_token) {
                    $this->invoiceRepo->addInvoice($expens);
                }

            } else {

                return response()->json(['status' => 'Error', 'message' => 'Invalid type'], 400);

            }
        }

        return response()->json(['status' => 'Success'], 200);
    }

    public function newFarmExpenses($attr)
    {
        foreach($attr['expenses'] as $expens) {

            if($expens['type'] == 's') {

                FarmExpenses::create([
                    'farm_id' => $expens['farm_id'],
                    'type' => 's',
                    'expenses_name' => $expens['expenses_name'],
                    'price_budget' => $expens['price_budget'],
                    'price_actual' => $expens['price_actual'],
                    'expense_date' => $expens['expense_date'],
                ]);

            } elseif ($expens['type'] == 'm') {
                FarmExpenses::create([
                    'farm_id' => $expens['farm_id'],
                    'type' => 'm',
                    'expenses_name' => $expens['expenses_name'],
                    'price_budget' => $expens['price_budget'],
                    'price_actual' => $expens['price_actual'],
                    'expense_date' => $expens['expense_date'],
                ]);
            } else {

                return response()->json(['status' => 'Error', 'message' => 'Invalid type'], 400);

            }
        }

        return response()->json(['status' => 'Success'], 200);
    }

    public function getBudgetByFarmOrLine($attr)
    {
        $u = auth()->user()->id;

        if(!empty($attr['farm_id'])) {

            $farm = $attr['farm_id'];

            $date = Carbon::createFromDate($attr['year'])->year;

            if(Carbon::now()->year != $date) {

                $startOfYear = Carbon::parse('first day of January ' . $date)->timestamp;

                $endOfYear = Carbon::parse('last day of December ' . $date)->timestamp;

                $budgets = Farm::whereHas('users', function ($q) use ($u) {
                    $q->where('user_id', '=', $u);
                })->where('id', $farm)
                    ->with('lines', function ($q) use ($startOfYear, $endOfYear) {
                        $q->with('budgets', function ($r) use ($startOfYear, $endOfYear) {
                            $r->where('start_budget', $startOfYear)
                                ->where('end_budget', $endOfYear);
                        });
                    })->get();

                return FarmBudgetResource::collection($budgets);

            } else {

                $startOfYear = Carbon::parse('first day of January ' . $date)->timestamp;

                $budgets = Farm::whereHas('users', function ($q) use ($u) {
                    $q->where('user_id', '=', $u);
                })->where('id', $farm)
                    ->with('lines', function ($q) use ($startOfYear) {
                        $q->with('budgets', function ($r) use ($startOfYear) {
                            $r->where('start_budget', $startOfYear)
                                ->where('end_budget', 0);
                        });
                    })->get();

                return FarmBudgetResource::collection($budgets);

            }
//            return BudgetFarmsLinesByResourse::collection($budgets);

        } else {

            $line = $attr['line_id'];

            $farm_id = Line::find($line);

            $date = Carbon::createFromDate($attr['year']);

            $startOfYear = $date->copy()->startOfYear()->timestamp;

            $endOfYear = $date->copy()->endOfYear()->timestamp;

            $budgets = Farm::whereHas('users')->with('lines_budgets', function($f) use ($line, $endOfYear, $startOfYear){
                            $f->with('budgets', function($r)  use ($line, $endOfYear, $startOfYear){
                                $r->where('line_id', '=', $line)
                                  ->where('start_budget', '=', $startOfYear)
                                  ->orWhere('end_budget', '=', $endOfYear);
                            });
                        })->where('id', '=', $farm_id->farm_id)->get();

            return BudgetFarmsLinesByPeriodResource::collection($budgets);
        }
    }

    public function updateBudget($attr)
    {
        if(isset($attr['budget_id'])) {

            try {

                $budget = LineBudget::find($attr['budget_id']);

                $budget[$attr['data_row']] = $attr['value'];
                // TODO add budget log logic
                $budget->save();

                return response()->json(['status' => 'Success'], 200);

            } catch (Exception $e) {

                return response()->json(['status' => 'Error', 'message' => 'Row does not updated'], 400);

            }
        } else {
            try {

                $budget = Line::find($attr['line_id']);

                $budget[$attr['data_row']] = $attr['value'];
                // TODO add budget log logic
                $budget->save();

                return response()->json(['status' => 'Success'], 200);

            } catch (Exception $e) {

                return response()->json(['status' => 'Error', 'message' => 'Row does not updated'], 400);

            }
        }
    }

    public function updateExpenses($attr)
    {
        try {

            $budget = Expenses::find($attr['expenses_id']);

            $acc = auth()->user()->getAccount();
            if ($attr['to_xero'] && $acc->xero_access_token) {
                $this->invoiceRepo->updateInvoice($budget, $attr);
                $attr['price_actual'] = $attr['value'];
                $merge = array_merge((array)json_decode($budget['rdata']), $attr);
                $budget['rdata'] = json_encode($merge);
            }

            $budget[$attr['data_row']] = $attr['value'];
            $budget['expense_date'] = $attr['expense_date'];
            $budget->save();

            return response()->json(['status' => 'Success'], 200);

        } catch (Exception $e) {

            return response()->json(['status' => 'Error', 'message' => 'Row does not updated'], 400);

        }
    }

    public function updateFarmExpenses($attr)
    {
        try {

            $budget = FarmExpenses::find($attr['expenses_id']);

            // $acc = auth()->user()->getAccount();
            // if ($attr['to_xero'] && $acc->xero_access_token) {
            //     $this->invoiceRepo->updateInvoice($budget, $attr);
            //     $attr['price_actual'] = $attr['value'];
            //     $merge = array_merge((array)json_decode($budget['rdata']), $attr);
            //     $budget['rdata'] = json_encode($merge);
            // }

            $budget[$attr['data_row']] = $attr['value'];
            $budget['expense_date'] = $attr['expense_date'];
            $budget->save();

            return response()->json(['status' => 'Success'], 200);

        } catch (Exception $e) {

            return response()->json(['status' => 'Error', 'message' => 'Row does not updated'], 400);

        }
    }
}

