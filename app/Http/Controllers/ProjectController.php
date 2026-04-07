<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Models\Company;

class ProjectController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function getCompany(int $companyId): object
    {
        $user    = Auth::user();
        $company = DB::table('companies')->where('id', $companyId)->first();
        abort_if(!$company, 404);

        if (!$user->is_super_admin && (int) $user->company_id !== (int) $companyId) {
            abort(403);
        }

        return $company;
    }

    /** Build full project data including tasks, assignees, logs, expenses */
    private function buildProjectDetail(int $projectId): array
    {
        $project = DB::table('projects')->where('id', $projectId)->first();
        if (!$project) return [];

        $tasks = DB::table('project_tasks')
            ->where('project_id', $projectId)
            ->orderBy('order')
            ->get();

        $taskIds = $tasks->pluck('id')->toArray();

        // Assignees per task
        $assignees = DB::table('project_task_assignees as pta')
            ->join('users as u', 'u.id', '=', 'pta.user_id')
            ->whereIn('pta.project_task_id', $taskIds)
            ->select('pta.project_task_id', 'u.id', 'u.name')
            ->get()
            ->groupBy('project_task_id');

        // Time logs per task
        $logs = DB::table('project_task_logs as ptl')
            ->join('users as u', 'u.id', '=', 'ptl.user_id')
            ->whereIn('ptl.project_task_id', $taskIds)
            ->select('ptl.*', 'u.name as user_name')
            ->orderByDesc('ptl.log_date')
            ->get()
            ->groupBy('project_task_id');

        // Total hours per task
        $hoursByTask = DB::table('project_task_logs')
            ->whereIn('project_task_id', $taskIds)
            ->select('project_task_id', DB::raw('SUM(hours) as total_hours'))
            ->groupBy('project_task_id')
            ->pluck('total_hours', 'project_task_id');

        // Expenses
        $expenses = DB::table('project_expenses as pe')
            ->join('users as u', 'u.id', '=', 'pe.created_by')
            ->where('pe.project_id', $projectId)
            ->select('pe.*', 'u.name as created_by_name')
            ->orderByDesc('pe.expense_date')
            ->get();

        $totalExternal = $expenses->sum('amount');

        // Internal labor cost (hours × user rate)
        $internalCost = 0;
        foreach ($logs->flatten() as $log) {
            $rate = DB::table('user_cost_rates')
                ->where('user_id', $log->user_id)
                ->where('company_id', $project->company_id)
                ->first();
            if ($rate) {
                $internalCost += $log->hours * ($rate->hourly_rate ?? ($rate->daily_rate / 8 ?? 0));
            }
        }

        $tasksFormatted = $tasks->map(function ($task) use ($assignees, $logs, $hoursByTask) {
            return [
                'id'                => $task->id,
                'name'              => $task->name,
                'description'       => $task->description,
                'status'            => $task->status,
                'priority'          => $task->priority,
                'order'             => $task->order,
                'estimated_days'    => $task->estimated_days,
                'start_date'        => $task->start_date,
                'due_date'          => $task->due_date,
                'progress_pct'      => $task->progress_pct,
                'depends_on_task_id'=> $task->depends_on_task_id,
                'assignees'         => collect($assignees->get($task->id, []))->map(fn($a) => ['id' => $a->id, 'name' => $a->name])->values(),
                'logs'              => collect($logs->get($task->id, []))->map(fn($l) => [
                    'id'           => $l->id,
                    'user_id'      => $l->user_id,
                    'user_name'    => $l->user_name,
                    'log_date'     => $l->log_date,
                    'hours'        => $l->hours,
                    'notes'        => $l->notes,
                    'progress_pct' => $l->progress_pct,
                ])->values(),
                'total_hours'       => (float) ($hoursByTask[$task->id] ?? 0),
            ];
        })->values();

        return [
            'id'               => $project->id,
            'name'             => $project->name,
            'description'      => $project->description,
            'phase'            => $project->phase,
            'status'           => $project->status,
            'start_date'       => $project->start_date,
            'end_date'         => $project->end_date,
            'currency'         => $project->currency,
            'created_at'       => $project->created_at,
            'tasks'            => $tasksFormatted,
            'expenses'         => $expenses->map(fn($e) => [
                'id'              => $e->id,
                'category'        => $e->category,
                'custom_category' => $e->custom_category ?? null,
                'display_category'=> !empty($e->custom_category) ? $e->custom_category : \App\Models\ProjectExpense::categoryLabel($e->category),
                'description'     => $e->description,
                'amount'          => (float) $e->amount,
                'expense_date'    => $e->expense_date,
                'created_by_name' => $e->created_by_name,
            ])->values(),
            'total_external_cost' => (float) $totalExternal,
            'total_internal_cost' => round($internalCost, 2),
            'total_project_cost'  => round($internalCost + $totalExternal, 2),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INDEX  GET /companies/{company}/projects
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Company $company)

    {
        $co = $this->getCompany($company->id);


        $projects = DB::table('projects as p')
            ->where('p.company_id', $company->id)
            ->orderByDesc('p.created_at')
            ->get();

        $projectIds = $projects->pluck('id')->toArray();

        // Task counts per project
        $taskCounts = DB::table('project_tasks')
            ->whereIn('project_id', $projectIds)
            ->select('project_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"))
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        // Total costs per project
        $expenseTotals = DB::table('project_expenses')
            ->whereIn('project_id', $projectIds)
            ->select('project_id', DB::raw('SUM(amount) as total'))
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        $formatted = $projects->map(function ($p) use ($taskCounts, $expenseTotals) {
            $tc = $taskCounts[$p->id] ?? null;
            return [
                'id'          => $p->id,
                'name'        => $p->name,
                'description' => $p->description,
                'phase'       => $p->phase,
                'status'      => $p->status,
                'start_date'  => $p->start_date,
                'end_date'    => $p->end_date,
                'currency'    => $p->currency,
                'task_total'  => $tc ? (int) $tc->total : 0,
                'task_done'   => $tc ? (int) $tc->completed : 0,
                'total_cost'  => (float) ($expenseTotals[$p->id] ?? 0),
                'created_at'  => $p->created_at,
            ];
        });

        // Users assigned to this company for task assignment
        $companyUsers = DB::table('users')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->select('id', 'name')
            ->get();

        // Cost rates for this company
        $costRates = DB::table('user_cost_rates')
            ->where('company_id', $company->id)
            ->get()
            ->keyBy('user_id');

        return Inertia::render('Projects/Index', [
            'company' => ['id' => $co->id, 'name' => $co->name],
            'projects'     => $formatted,
            'companyUsers' => $companyUsers,
            'costRates'    => $costRates,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHOW  GET /companies/{company}/projects/{project}
    // ─────────────────────────────────────────────────────────────────────────
    public function show(int $company, int $project)
    {
        $co = $this->getCompany($company);

        $proj = DB::table('projects')->where('id', $project)->where('company_id', $company)->first();
        abort_if(!$proj, 404);

        $data = $this->buildProjectDetail($project);

        $companyUsers = DB::table('users')
            ->where('company_id', $company)
            ->where('is_active', true)
            ->select('id', 'name')
            ->get();

        return Inertia::render('Projects/Show', [
            'company'      => ['id' => $co->id, 'name' => $co->name],
            'project'      => $data,
            'companyUsers' => $companyUsers,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STORE  POST /companies/{company}/projects
    // ─────────────────────────────────────────────────────────────────────────
    public function store(Request $request, int $company)
    {
        $this->getCompany($company);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'phase'       => 'nullable|string|max:255',
            'status'      => 'required|in:not_started,in_progress,on_hold,completed,cancelled',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date',
            'currency'    => 'nullable|string|max:10',
        ]);

        $id = DB::table('projects')->insertGetId(array_merge($data, [
            'company_id' => $company,
            'created_by'           => Auth::id(),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]));

        return response()->json(['success' => true, 'id' => $id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE  PUT /companies/{company}/projects/{project}
    // ─────────────────────────────────────────────────────────────────────────
    public function update(Request $request, int $company, int $project)
    {
        $this->getCompany($company);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'phase'       => 'nullable|string|max:255',
            'status'      => 'required|in:not_started,in_progress,on_hold,completed,cancelled',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date',
            'currency'    => 'nullable|string|max:10',
        ]);

        DB::table('projects')->where('id', $project)->update(array_merge($data, ['updated_at' => now()]));

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DESTROY  DELETE /companies/{company}/projects/{project}
    // ─────────────────────────────────────────────────────────────────────────
    public function destroy(int $company, int $project)
    {
        $this->getCompany($company);
        DB::table('projects')->where('id', $project)->where('company_id', $company)->delete();
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STORE TASK  POST /companies/{company}/projects/{project}/tasks
    // ─────────────────────────────────────────────────────────────────────────
    public function storeTask(Request $request, int $company, int $project)
    {
        $this->getCompany($company);

        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'description'        => 'nullable|string',
            'priority'           => 'required|in:low,medium,high',
            'status'             => 'required|in:not_started,in_progress,completed,blocked',
            'estimated_days'     => 'nullable|integer|min:1',
            'start_date'         => 'nullable|date',
            'due_date'           => 'nullable|date',
            'depends_on_task_id' => 'nullable|exists:project_tasks,id',
            'assignee_ids'       => 'nullable|array',
            'assignee_ids.*'     => 'exists:users,id',
        ]);

        // Get max order
        $maxOrder = DB::table('project_tasks')->where('project_id', $project)->max('order') ?? 0;

        $taskId = DB::table('project_tasks')->insertGetId([
            'project_id'          => $project,
            'created_by'          => Auth::id(),
            'name'                => $data['name'],
            'description'         => $data['description'] ?? null,
            'priority'            => $data['priority'],
            'status'              => $data['status'],
            'estimated_days'      => $data['estimated_days'] ?? null,
            'start_date'          => $data['start_date'] ?? null,
            'due_date'            => $data['due_date'] ?? null,
            'depends_on_task_id'  => $data['depends_on_task_id'] ?? null,
            'progress_pct'        => 0,
            'order'               => $maxOrder + 1,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // Assign users
        foreach ($data['assignee_ids'] ?? [] as $uid) {
            DB::table('project_task_assignees')->insertOrIgnore([
                'project_task_id' => $taskId,
                'user_id'         => $uid,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        return response()->json(['success' => true, 'id' => $taskId]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE TASK  PUT /companies/{company}/projects/{project}/tasks/{task}
    // ─────────────────────────────────────────────────────────────────────────
    public function updateTask(Request $request, int $company, int $project, int $task)
    {
        $this->getCompany($company);

        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'description'        => 'nullable|string',
            'priority'           => 'required|in:low,medium,high',
            'status'             => 'required|in:not_started,in_progress,completed,blocked',
            'estimated_days'     => 'nullable|integer|min:1',
            'start_date'         => 'nullable|date',
            'due_date'           => 'nullable|date',
            'progress_pct'       => 'nullable|integer|min:0|max:100',
            'depends_on_task_id' => 'nullable|exists:project_tasks,id',
            'assignee_ids'       => 'nullable|array',
            'assignee_ids.*'     => 'exists:users,id',
        ]);

        DB::table('project_tasks')->where('id', $task)->update([
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'priority'           => $data['priority'],
            'status'             => $data['status'],
            'estimated_days'     => $data['estimated_days'] ?? null,
            'start_date'         => $data['start_date'] ?? null,
            'due_date'           => $data['due_date'] ?? null,
            'progress_pct'       => $data['progress_pct'] ?? 0,
            'depends_on_task_id' => $data['depends_on_task_id'] ?? null,
            'updated_at'         => now(),
        ]);

        // Re-sync assignees
        DB::table('project_task_assignees')->where('project_task_id', $task)->delete();
        foreach ($data['assignee_ids'] ?? [] as $uid) {
            DB::table('project_task_assignees')->insertOrIgnore([
                'project_task_id' => $task,
                'user_id'         => $uid,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE TASK
    // ─────────────────────────────────────────────────────────────────────────
    public function destroyTask(int $company, int $project, int $task)
    {
        $this->getCompany($company);
        DB::table('project_tasks')->where('id', $task)->where('project_id', $project)->delete();
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LOG TIME  POST /projects/{project}/tasks/{task}/logs
    // ─────────────────────────────────────────────────────────────────────────
    public function storeLog(Request $request, int $company, int $project, int $task)
    {
        $this->getCompany($company);

        $data = $request->validate([
            'log_date'     => 'required|date',
            'hours'        => 'required|numeric|min:0.25|max:24',
            'notes'        => 'nullable|string',
            'progress_pct' => 'nullable|integer|min:0|max:100',
        ]);

        $id = DB::table('project_task_logs')->insertGetId(array_merge($data, [
            'project_task_id' => $task,
            'user_id'         => Auth::id(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]));

        // Update task progress if provided
        if (!empty($data['progress_pct'])) {
            DB::table('project_tasks')->where('id', $task)
                ->update(['progress_pct' => $data['progress_pct'], 'updated_at' => now()]);
        }

        return response()->json(['success' => true, 'id' => $id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE LOG
    // ─────────────────────────────────────────────────────────────────────────
    public function destroyLog(int $company, int $project, int $task, int $log)
    {
        $this->getCompany($company);
        DB::table('project_task_logs')->where('id', $log)->where('project_task_id', $task)->delete();
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STORE EXPENSE  POST /projects/{project}/expenses
    // ─────────────────────────────────────────────────────────────────────────
    public function storeExpense(Request $request, int $company, int $project)
    {
        $this->getCompany($company);

        $data = $request->validate([
            'category'        => 'required|in:consultant,freelancer,legal,accounting,software,saas_subscription,hardware,purchase,raw_materials,travel,accommodation,marketing,training,government_fees,bank_charges,insurance,maintenance,logistics,other',
            'custom_category' => 'nullable|string|max:100',
            'description'     => 'required|string|max:500',
            'amount'          => 'required|numeric|min:0',
            'expense_date'    => 'required|date',
            'receipt'         => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store("project-receipts/{$project}", 'local');
        }

        $id = DB::table('project_expenses')->insertGetId([
            'project_id'      => $project,
            'created_by'      => Auth::id(),
            'category'        => $data['category'],
            'custom_category' => $data['custom_category'] ?? null,
            'description'     => $data['description'],
            'amount'          => $data['amount'],
            'expense_date'    => $data['expense_date'],
            'receipt_path'    => $receiptPath,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE EXPENSE
    // ─────────────────────────────────────────────────────────────────────────
    public function destroyExpense(int $company, int $project, int $expense)
    {
        $this->getCompany($company);
        $exp = DB::table('project_expenses')->where('id', $expense)->first();
        if ($exp && $exp->receipt_path) {
            Storage::disk('local')->delete($exp->receipt_path);
        }
        DB::table('project_expenses')->where('id', $expense)->delete();
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SAVE COST RATES  POST /companies/{company}/projects/cost-rates
    // ─────────────────────────────────────────────────────────────────────────
    public function saveCostRates(Request $request, int $company)
    {
        $this->getCompany($company);

        $data = $request->validate([
            'rates'              => 'required|array',
            'rates.*.user_id'    => 'required|exists:users,id',
            'rates.*.daily_rate' => 'nullable|numeric|min:0',
            'rates.*.hourly_rate'=> 'nullable|numeric|min:0',
            'rates.*.currency'   => 'nullable|string|max:10',
        ]);

        foreach ($data['rates'] as $rate) {
            DB::table('user_cost_rates')->updateOrInsert(
                ['user_id' => $rate['user_id'], 'company_id' => $company],
                [
                    'daily_rate'  => $rate['daily_rate'] ?? null,
                    'hourly_rate' => $rate['hourly_rate'] ?? null,
                    'currency'    => $rate['currency'] ?? 'USD',
                    'updated_at'  => now(),
                    'created_at'  => now(),
                ]
            );
        }

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REORDER TASKS  POST /projects/{project}/tasks/reorder
    // ─────────────────────────────────────────────────────────────────────────
    public function reorderTasks(Request $request, int $company, int $project)
    {
        $this->getCompany($company);

        $data = $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:project_tasks,id',
        ]);

        foreach ($data['order'] as $index => $taskId) {
            DB::table('project_tasks')->where('id', $taskId)
                ->update(['order' => $index, 'updated_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REFRESH (returns updated project JSON for Vue reactivity)
    // ─────────────────────────────────────────────────────────────────────────
    public function refresh(int $company, int $project)
    {
        $this->getCompany($company);
        return response()->json($this->buildProjectDetail($project));
    }
}