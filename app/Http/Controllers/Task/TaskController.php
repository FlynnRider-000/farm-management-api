<?php

namespace App\Http\Controllers\Task;

use App\Models\Task;
use App\Models\Inviting;
use App\Models\User;
use App\Http\Requests\Task\TaskRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $tasks = [];
        $account = auth()->user()->getAccount($request->input('account_id'));
        if($account->pivot->hasRole('owner') || $account->pivot->hasRole('admin')) {

            $tasks = Task::where('account_id', $request->input('account_id'))->get();

        } else {

            $tasks = Task::where('account_id', $request->input('account_id'))
                        ->where(function ($q){
                            $q->where('creator_id', auth()->user()->id)
                                ->orWhere('assigned_to', auth()->user()->id)->get();
                        })->get();
        }

        return response()->json([
            'status' => 'success',
            'data' => $tasks,
        ], 200);
    }

    public function store(TaskRequest $request)
    {
        $attr = $request->validated();

        $task = Task::create([
            'creator_id' => auth()->user()->id,
            'account_id' => $attr['account_id'],
            'farm_id' => $attr['farm_id'],
            'title' => $attr['title'],
            'content' => $attr['content'],
            'assigned_to' => $attr['assigned_to'],
            'line_id' => $attr['line_id'],
            'due_date' => $attr['due_date'],
        ]);

        return response()->json(['status' => 'success'], 200);
    }
    
    public function update(TaskRequest $request, $id)
    {
        $attr = $request->validated();

        $task = Task::find($id);

        $task->farm_id = $attr['farm_id'];
        $task->line_id = $attr['line_id'];
        $task->assigned_to = $attr['assigned_to'];
        $task->title = $attr['title'];
        $task->content = $attr['content'];
        $task->due_date = $attr['due_date'];
        $task->active = $attr['active'];

        $task->save();
        return response()->json(['status' => 'success'], 200);
    }

    public function destroy($id)
    {
        $task = Task::find($id);
        $task->delete();
        return response()->json(['status' => 'success'], 200);
    }

    public function removeCompletedTasks()
    {
        Task::where('active', 1)->delete();
        return response()->json(['status' => 'success'], 200);
    }
}
