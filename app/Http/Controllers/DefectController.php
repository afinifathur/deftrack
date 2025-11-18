<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Defect;
use App\Models\DefectLine;
use App\Models\Department;
use App\Models\DefectType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DefectController extends Controller
{
    protected function authorizeRole(array $allowedRoles): void
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, $allowedRoles, true)) {
            abort(403, 'Forbidden');
        }
    }

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

    public function show(Defect $defect)
    {
        $defect->load(['department', 'lines.batch', 'lines.defectType']);
        return view('defects.show', compact('defect'));
    }

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
     *
     * Accepts:
     * - structured: lines => [ ['heat_number'=>..., 'item_code'=>..., ...], ... ]
     * - or parallel arrays: heat_number[], item_code[], defect_type_id[], subtype_id[], qty_pcs[], qty_kg[], batch_code[]
     */
    public function store(Request $request)
    {
        $this->authorizeRole(['admin_qc', 'kabag_qc']);

        $data = $request->validate([
            'date'          => ['required', 'date'],
            'department_id' => ['required', 'exists:departments,id'],
            'notes'         => ['nullable', 'string'],
            'status'        => ['nullable', 'string'], // optional: draft/submitted
        ]);

        // Build unified $lines array from either 'lines' structured input or parallel arrays
        $lines = [];

        if ($request->has('lines') && is_array($request->input('lines'))) {
            // structured input: lines[*][field]
            foreach ($request->input('lines') as $raw) {
                if (!is_array($raw)) continue;
                $heat = trim($raw['heat_number'] ?? '');
                $item = trim($raw['item_code'] ?? '');
                $pcs  = isset($raw['qty_pcs']) ? (int)$raw['qty_pcs'] : 0;
                $type = $raw['defect_type_id'] ?? null;

                // skip empty rows (same criteria as frontend)
                if ($heat === '' && $item === '' && $pcs <= 0 && empty($type)) {
                    continue;
                }

                $lines[] = [
                    'heat_number'     => $heat !== '' ? $heat : null,
                    'item_code'       => $item !== '' ? $item : null,
                    'defect_type_id'  => $type ?: null,
                    'subtype_id'      => $raw['subtype_id'] ?? null,
                    'qty_pcs'         => $pcs,
                    'qty_kg'          => isset($raw['qty_kg']) ? (float)$raw['qty_kg'] : 0.0,
                    'batch_code'      => $raw['batch_code'] ?? null,
                ];
            }
        } else {
            // parallel arrays input (heat_number[], item_code[], ...)
            $heats      = $request->input('heat_number', []);
            $items      = $request->input('item_code', []);
            $types      = $request->input('defect_type_id', []);
            $subtypes   = $request->input('subtype_id', []);
            $qtyPcs     = $request->input('qty_pcs', []);
            $qtyKg      = $request->input('qty_kg', []);
            $batchCodes = $request->input('batch_code', []);

            $count = max(
                count($heats), count($items), count($types),
                count($subtypes), count($qtyPcs), count($qtyKg), count($batchCodes)
            );

            for ($i = 0; $i < $count; $i++) {
                $heat = trim($heats[$i] ?? '');
                $item = trim($items[$i] ?? '');
                $pcs  = isset($qtyPcs[$i]) ? (int)$qtyPcs[$i] : 0;
                $type = $types[$i] ?? null;

                if ($heat === '' && $item === '' && $pcs <= 0 && empty($type)) {
                    continue;
                }

                $lines[] = [
                    'heat_number'     => $heat !== '' ? $heat : null,
                    'item_code'       => $item !== '' ? $item : null,
                    'defect_type_id'  => $type ?: null,
                    'subtype_id'      => $subtypes[$i] ?? null,
                    'qty_pcs'         => $pcs,
                    'qty_kg'          => isset($qtyKg[$i]) ? (float)$qtyKg[$i] : 0.0,
                    'batch_code'      => $batchCodes[$i] ?? null,
                ];
            }
        }

        if (empty($lines)) {
            // Allow empty lines but warn — we still create parent if user wants (mirrors frontend confirmation)
            Log::info('Saving defect with no lines', ['user_id' => auth()->id(), 'department_id' => $data['department_id'] ?? null]);
        }

        DB::transaction(function () use ($data, $lines) {
            $defect = Defect::create([
                'date'          => $data['date'],
                'department_id' => $data['department_id'],
                'notes'         => $data['notes'] ?? null,
                'status'        => $data['status'] ?? 'draft',
                'submitted_by'  => auth()->id() ?? 1,
            ]);

            foreach ($lines as $idx => $ln) {
                // Basic per-line validation: must have heat OR item OR defect_type
                if (empty($ln['heat_number']) && empty($ln['item_code']) && empty($ln['defect_type_id'])) {
                    continue;
                }

                $batch = null;

                if (!empty($ln['heat_number']) && !empty($ln['item_code'])) {
                    $batch = Batch::where('heat_number', $ln['heat_number'])
                        ->where('item_code', $ln['item_code'])
                        ->first();
                } elseif (!empty($ln['heat_number'])) {
                    // try find by heat only
                    $batch = Batch::where('heat_number', $ln['heat_number'])->latest('cast_date')->first();
                }

                if (!$batch) {
                    // create lightweight Batch record (ad-hoc) so defect lines can reference a batch
                    $batchCode = $ln['batch_code'] ?? Batch::generateBatchCode($data['date'] ?? Carbon::now()->toDateString());
                    $batch = Batch::create([
                        'heat_number'       => $ln['heat_number'] ?? null,
                        'item_code'         => $ln['item_code'] ?? null,
                        'item_name'         => null,
                        'weight_per_pc'     => null,
                        'batch_qty'         => 0,
                        'cast_date'         => $data['date'] ?? Carbon::now()->toDateString(),
                        'import_session_id' => null,
                        'batch_code'        => $batchCode,
                    ]);

                    Log::info('Created ad-hoc batch for defect (store)', [
                        'batch_id' => $batch->id,
                        'heat' => $ln['heat_number'] ?? null,
                        'item' => $ln['item_code'] ?? null,
                        'source' => 'defect.store'
                    ]);
                }

                // check qty constraint only if batch.batch_qty > 0 (if 0 it's ad-hoc; skip check)
                $incoming = isset($ln['qty_pcs']) ? (int)$ln['qty_pcs'] : 0;
                if ($batch->batch_qty > 0) {
                    $currentSum = (int) DefectLine::where('batch_id', $batch->id)->sum('qty_pcs');
                    if ($currentSum + $incoming > $batch->batch_qty) {
                        // throw validation style exception
                        throw ValidationException::withMessages([
                            "lines.{$idx}.qty_pcs" => ["Total defect qty exceeds batch_qty for heat {$batch->heat_number} / {$batch->item_code}"]
                        ]);
                    }
                }

                // create defect line (link to batch)
                DefectLine::create([
                    'defect_id'      => $defect->id,
                    'batch_id'       => $batch->id,
                    'defect_type_id' => $ln['defect_type_id'],
                    'subtype_id'     => $ln['subtype_id'] ?? null,
                    'qty_pcs'        => $incoming,
                    'qty_kg'         => $ln['qty_kg'] ?? 0.0,
                ]);
            }
        });

        return redirect()->route('defects.index')->with('status', 'Defect disimpan sebagai draft.');
    }

    public function submit(Defect $defect)
    {
        $user = auth()->user();
        $userRole = $user?->role;

        if ($userRole === 'admin_qc' && $user->department_id !== $defect->department_id) {
            abort(403);
        }

        $this->authorizeRole(['admin_qc', 'kabag_qc']);

        $defect->update([
            'status' => 'submitted',
            'submitted_by' => auth()->id() ?? $defect->submitted_by,
        ]);

        return back()->with('status', 'Defect submitted ke Kabag QC.');
    }

    public function destroy(Defect $defect)
    {
        $this->authorizeRole(['kabag_qc', 'direktur', 'mr']);

        $defect->delete();

        return back()->with('success', 'Defect berhasil dipindah ke recycle.');
    }

    public function recycle()
    {
        $this->authorizeRole(['kabag_qc', 'direktur', 'mr']);

        $deleted = Defect::onlyTrashed()->with('department')->orderByDesc('deleted_at')->paginate(30);

        return view('defects.recycle', compact('deleted'));
    }

    public function restore($id)
    {
        $this->authorizeRole(['kabag_qc', 'direktur', 'mr']);

        $d = Defect::onlyTrashed()->findOrFail($id);
        $d->restore();

        return back()->with('success', 'Defect restored.');
    }

    public function edit(Defect $defect)
    {
        $user = auth()->user();
        $userRole = $user?->role;

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

        $validated = $request->validate([
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

        DB::transaction(function () use ($validated, $defect) {
            $defect->update($validated + ['notes' => $validated['notes'] ?? $defect->notes]);

            $existingLines = $defect->lines()->get()->keyBy('id');
            $seenIds = [];

            foreach ($validated['lines'] as $line) {
                if (!empty($line['id'])) {
                    $lineId = (int) $line['id'];
                    $seenIds[] = $lineId;

                    $dl = DefectLine::find($lineId);
                    if (!$dl) continue;

                    $incoming = isset($line['qty_pcs']) ? (int) $line['qty_pcs'] : $dl->qty_pcs;

                    $batch = $dl->batch()->first();
                    if ($batch) {
                        $other_sum = (int) DefectLine::where('batch_id', $batch->id)
                            ->where('id', '!=', $dl->id)
                            ->sum('qty_pcs');

                        if ($other_sum + $incoming > $batch->batch_qty) {
                            throw ValidationException::withMessages([
                                'lines' => ["Total defect qty exceeds batch_qty for heat {$batch->heat_number} / {$batch->item_code}"]
                            ]);
                        }
                    }

                    $dl->update([
                        'defect_type_id' => $line['defect_type_id'] ?? $dl->defect_type_id,
                        'subtype_id'     => $line['subtype_id'] ?? $dl->subtype_id,
                        'qty_pcs'        => $incoming,
                        'qty_kg'         => $line['qty_kg'] ?? $dl->qty_kg,
                    ]);
                } else {
                    $heat = $line['heat_number'] ?? null;
                    $item = $line['item_code'] ?? null;
                    if (!$heat || !$item) {
                        continue;
                    }

                    $batch = Batch::where('heat_number', $heat)
                        ->where('item_code', $item)
                        ->first();

                    if (!$batch) {
                        Log::warning('Batch not found when creating defect line (update)', ['heat' => $heat, 'item' => $item]);
                        continue;
                    }

                    $incoming = isset($line['qty_pcs']) ? (int) $line['qty_pcs'] : 0;
                    $currentSum = (int) DefectLine::where('batch_id', $batch->id)->sum('qty_pcs');

                    if ($currentSum + $incoming > $batch->batch_qty) {
                        throw ValidationException::withMessages([
                            'lines' => ["Total defect qty exceeds batch_qty for heat {$batch->heat_number} / {$batch->item_code}"]
                        ]);
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

            $toDelete = $existingLines->keys()->diff($seenIds);
            if ($toDelete->isNotEmpty()) {
                DefectLine::whereIn('id', $toDelete->all())->delete();
            }
        });

        return redirect()->route('defects.show', $defect->id)->with('success', 'Defect updated.');
    }
}
