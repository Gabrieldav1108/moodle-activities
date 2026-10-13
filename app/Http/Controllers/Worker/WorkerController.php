<?php

namespace App\Http\Controllers\Worker;

use App\Actions\ClaimNextRun;
use App\Actions\StoreAssignmentResult;
use App\Enums\RunStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Worker\AppendLogRequest;
use App\Http\Requests\Worker\FinishRunRequest;
use App\Http\Requests\Worker\UploadAssignmentRequest;
use App\Models\Run;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class WorkerController extends Controller
{
    public function claim(ClaimNextRun $claim): JsonResponse|Response
    {
        $payload = $claim->handle();

        return $payload ? response()->json($payload) : response()->noContent();
    }

    public function log(AppendLogRequest $request, Run $run): Response
    {
        $run->appendLog($request->validated('lines'));
        Setting::current()->forceFill(['worker_seen_at' => now()])->save();

        return response()->noContent();
    }

    public function assignment(UploadAssignmentRequest $request, Run $run, StoreAssignmentResult $store): JsonResponse
    {
        abort_unless($run->status === RunStatus::Running, 409, 'Run is not running.');

        $assignment = $store->handle(
            $run,
            $request->validated('meta'),
            $request->validated('draft') ?? [],
            array_values(Arr::wrap($request->file('attachments', []))),
        );

        return response()->json(['id' => $assignment->id], 201);
    }

    public function finish(FinishRunRequest $request, Run $run): Response
    {
        $run->update([
            'status' => $request->validated('status'),
            'error' => $request->validated('error'),
            'finished_at' => now(),
        ]);

        return response()->noContent();
    }
}
