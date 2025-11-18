<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Defect;
use App\Models\DefectLine;
use App\Models\Department;
use App\Models\DefectType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;

class DefectController extends Controller
{
    /**
     * Minimal role authorizer (wraps inline checks).
     * Replace with Policies/Gates for production.
     */
    protected function authorizeRole(array $allowedRoles)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, $allowedRoles, true)) {
            abort(403, 'Forbidden');
        }
    }

    /**
     * Index: list defects with optional search + filter.
     * Query params:
     *  - q: search heat_number, batch_code, item_code, item_name (via lines->batch)
     *  - department_id
     */
    public function index(Request $request)
    {
        $q = (string) $request->query('q', '');
        $dept = $request->query('department_id');

        $query = Defect::with(['department', 'lines.batch', 'lines.defectType'])
            ->when($dept, fn($qb) => $qb->where('department_id', $dept))
            ->when($q !== '', function ($qb) use ($q) {
                $qb->whereHas('lines.batch', function ($bq) use ($q) {
                    $bq->where('heat_number', 'like', "%{$q}%")
                       ->orWhere('batch_code', 'like', "%{$q}%")
                       ->orWhere('item_code', 'like', "%{$q}%")
                       ->orWhere('item_name', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('date');

        $defects = $query->paginate(20)->withQueryString();

        $departments = Department::where('is_active', 1)->orderBy('name')->get();

        return view('defects.index', compact('defects', 'departments'));
    }

    /**
     * Show single defect (with lines).
     */
    public function show(Defect $defect)
    {
        $defect->load(['department', 'lines.batch', 'lines.defectType']);
        return view('defects.show', compact('defect'));
    }

    /**
     * Create form.
     */
    public function create()
    {
        $departments = Department::where('is_active', 1)->orderBy('name')->get();

        $types = DefectType::whereNull('parent_id')
            ->with(['children:id,name,parent_id'])
            ->get();

        $typeTree = $types->map(function ($t) {
            return [
                'id'       => $t->id,
                'name'     => $t->name,
                'children' => $t->children->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
            ];
        })->values()->all();

        return view('defects.create', compact('departments', 'types', 'typeTree'));
    }

    /**
     * Store defect + lines as draft.
     */
    public function store(Request $request)
    {
        // only admin_qc or kabag_qc can create (per your routes this group already protected)
        $this->authorizeRole(['admin_qc', 'kabag_qc']);

        $request->validate([
            'date'                     => ['required', 'date'],
            'department_id'            => ['required', 'exists:departments,id'],
            'lines'                    => ['required', 'array', 'min:1'],
            'lines.*.heat_number'      => ['required', 'string'],
            'lines.*.item_code'        => ['required', 'string'],
            'lines.*.defect_type_id'   => ['required', 'exists:defect_types,id'],
            'lines.*.qty_pcs'          => ['nullable', 'numeric', 'min:0'],
            'lines.*.qty_kg'           => ['nullable', 'numeric', 'min:0'],
            'lines.*.subtype_id'       => ['nullable', 'exists:defect_types,id'],
        ]);

        DB::transaction(function () use ($request) {
            $defect = Defect::create([
                'date'          => $request->date,
                'department_id' => $request->department_id,
                'status'        => 'draft',
                'submitted_by'  => auth()->id() ?? 1,
            ]);

            foreach ($request->lines as $line) {
                $batch = Batch::where('heat_number', $line['heat_number'])
                    ->where('item_code', $line['item_code'])
                    ->first();

                if (!$batch) {
                    // skip if no matching batch
                    continue;
                }

                $current  = DefectLine::where('batch_id', $batch->id)->sum('qty_pcs');
                $incoming = (int) ($line['qty_pcs'] ?? 0);

                if ($current + $incoming > $batch->batch_qty) {
                    abort(422, 'Total defect qty melebihi batch_qty untuk heat '
                        . $batch->heat_number . ' / ' . $batch->item_code);
                }

                DefectLine::create([
                    'defect_id'      => $defect->id,
                    'batch_id'       => $batch->id,
                    'defect_type_id' => $line['defect_type_id'],
                    'subtype_id'     => $line['subtype_id'] ?? null,
                    'qty_pcs'        => $incoming,
                    'qty_kg'         => $line['qty_kg'] ?? 0,
                ]);
            }
        });

        return redirect()->route('defects.index')->with('status', 'Defect disimpan sebagai draft.');
    }

    /**
     * Submit defect => set status submitted.
     */
    public function submit(Defect $defect)
    {
        // allow submit only for admin_qc in same dept OR kabag_qc
        $userRole = auth()->user()?->role;
        if ($userRole === 'admin_qc' && auth()->user()->department_id !== $defect->department_id) {
            abort(403);
        }
        $this->authorizeRole(['admin_qc', 'kabag_qc']);

        $defect->update(['status' => 'submitted', 'submitted_by' => auth()->id() ?? $defect->submitted_by]);

        return back()->with('status', 'Defect submitted ke Kabag QC.');
    }

    /**
     * Soft delete (move to recycle).
     */
    public function destroy(Defect $defect)
    {
        $this->authorizeRole(['kabag_qc', 'direktur', 'mr']);

        $defect->delete();

        return back()->with('success', 'Defect berhasil dipindah ke recycle.');
    }

    /**
     * Recycle: list soft-deleted defects (only for certain roles).
     */
    public function recycle()
    {
        $this->authorizeRole(['kabag_qc', 'direktur', 'mr']);

        $deleted = Defect::onlyTrashed()->with('department')->orderByDesc('deleted_at')->paginate(30);

        return view('defects.recycle', compact('deleted'));
    }

    /**
     * Restore a soft-deleted defect (by id).
     */
    public function restore($id)
    {
        $this->authorizeRole(['kabag_qc', 'direktur', 'mr']);

        $d = Defect::onlyTrashed()->findOrFail($id);
        $d->restore();

        return back()->with('success', 'Defect restored.');
    }

    /**
     * Edit form.
     */
    public function edit(Defect $defect)
    {
        $user = auth()->user();
        $userRole = $user?->role;

        // permissions
        if (!in_array($userRole, ['admin_qc', 'kabag_qc', 'direktur', 'mr'], true)) {
            abort(403);
        }
        if ($userRole === 'admin_qc' && $user->department_id && $user->department_id !== $defect->department_id) {
            abort(403);
        }

        $departments = Department::where('is_active', 1)->orderBy('name')->get();
        $types = DefectType::with('children')->whereNull('parent_id')->get();
        $defect->load(['lines.batch', 'lines.defectType']);

        return view('defects.edit', compact('defect', 'types', 'departments'));
    }

    /**
     * Update parent + child lines.
     * Strategy: validate, then transactionally update/create/delete child lines.
     */
    public function update(Request $request, Defect $defect)
    {
        $user = auth()->user();
        $userRole = $user?->role;

        if (!in_array($userRole, ['admin_qc', 'kabag_qc', 'direktur', 'mr'], true)) {
            abort(403);
        }
        if ($userRole === 'admin_qc' && $user->department_id && $user->department_id !== $defect->department_id) {
            abort(403);
        }

        $request->validate([
            'date'                     => ['required', 'date'],
            'department_id'            => ['required', 'exists:departments,id'],
            'notes'                    => ['nullable', 'string'],
            'lines'                    => ['required', 'array'],
            'lines.*.id'               => ['nullable', 'integer', 'exists:defect_lines,id'],
            'lines.*.heat_number'      => ['required_without:lines.*.id', 'string'],
            'lines.*.item_code'        => ['required_without:lines.*.id', 'string'],
            'lines.*.defect_type_id'   => ['required', 'exists:defect_types,id'],
            'lines.*.qty_pcs'          => ['nullable', 'numeric', 'min:0'],
            'lines.*.qty_kg'           => ['nullable', 'numeric', 'min:0'],
            'lines.*.subtype_id'       => ['nullable', 'exists:defect_types,id'],
        ]);

        DB::transaction(function () use ($request, $defect) {
            $defect->update($request->only(['date', 'department_id', 'notes']));

            $existingLines = $defect->lines()->get()->keyBy('id');
            $seenIds = [];

            foreach ($request->lines as $line) {
                // update existing line
                if (!empty($line['id'])) {
                    $seenIds[] = (int) $line['id'];
                    $dl = DefectLine::find($line['id']);
                    if (!$dl) continue;

                    $incoming = isset($line['qty_pcs']) ? (int) $line['qty_pcs'] : $dl->qty_pcs;
                    $batch = $dl->batch()->first();
                    if ($batch) {
                        $other_sum = DefectLine::where('batch_id', $batch->id)
                            ->where('id', '!=', $dl->id)
                            ->sum('qty_pcs');

                        if ($other_sum + $incoming > $batch->batch_qty) {
                            abort(422, 'Total defect qty melebihi batch_qty untuk heat '
                                . $batch->heat_number . ' / ' . $batch->item_code);
                        }
                    }

                    $dl->update([
                        'defect_type_id' => $line['defect_type_id'] ?? $dl->defect_type_id,
                        'subtype_id'     => $line['subtype_id'] ?? $dl->subtype_id,
                        'qty_pcs'        => $incoming,
                        'qty_kg'         => $line['qty_kg'] ?? $dl->qty_kg,
                    ]);
                } else {
                    // create new line (must find batch)
                    $batch = Batch::where('heat_number', $line['heat_number'])
                        ->where('item_code', $line['item_code'])
                        ->first();

                    if (!$batch) {
                        // skip if batch not found
                        continue;
                    }

                    $incoming = (int) ($line['qty_pcs'] ?? 0);
                    $currentSum = DefectLine::where('batch_id', $batch->id)->sum('qty_pcs');

                    if ($currentSum + $incoming > $batch->batch_qty) {
                        abort(422, 'Total defect qty melebihi batch_qty untuk heat '
                            . $batch->heat_number . ' / ' . $batch->item_code);
                    }

                    $new = DefectLine::create([
                        'defect_id'      => $defect->id,
                        'batch_id'       => $batch->id,
                        'defect_type_id' => $line['defect_type_id'],
                        'subtype_id'     => $line['subtype_id'] ?? null,
                        'qty_pcs'        => $incoming,
                        'qty_kg'         => $line['qty_kg'] ?? 0,
                    ]);

                    $seenIds[] = $new->id;
                }
            }

            // delete lines that were removed in request
            $toDelete = $existingLines->keys()->diff($seenIds);
            if ($toDelete->isNotEmpty()) {
                DefectLine::whereIn('id', $toDelete->all())->delete();
            }
        });

        return redirect()->route('defects.show', $defect->id)->with('success', 'Defect updated.');
    }
}
