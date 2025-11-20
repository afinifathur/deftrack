<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Defect;
use App\Models\DefectLine;
use App\Models\Department;
use App\Models\DefectType;
use App\Models\DefectCategory;
use App\Models\DefectTypeVariant;
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
        // departments for the form
        $departments = Department::where('is_active', 1)->orderBy('name')->get();

        // defect types tree (existing behavior)
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

        // load defect categories with active variants (for admin/lookup)
        $categories = DefectCategory::with(['variants' => function ($q) {
            $q->where('active', 1)->orderBy('ordering');
        }])->get();

        return view('defects.create', compact('departments', 'types', 'typeTree', 'categories'));
    }

    /**
     * Store defect + lines as draft.
     * Auto-assign variant based on department+category, compute qty_kg server-side.
     */
    public function store(Request $request)
    {
        $this->authorizeRole(['admin_qc', 'kabag_qc']);

        $data = $request->validate([
            'date'          => ['required', 'date'],
            'department_id' => ['required', 'exists:departments,id'],
            'notes'         => ['nullable', 'string'],
            'status'        => ['nullable', 'string'], // optional: draft/submitted
            // lines validation is handled loosely; we normalize later
        ]);

        // Normalize lines (accept structured lines[] or parallel arrays)
        $lines = $this->normalizeLinesFromRequest($request);

        if (empty($lines)) {
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

            $departmentId = $data['department_id'];

            foreach ($lines as $idx => $ln) {
                // require at least heat/item/defect_type
                if (empty($ln['heat_number']) && empty($ln['item_code']) && empty($ln['defect_type_id'])) {
                    continue;
                }

                // find batch: heat+item, else heat latest, else batch_code
                $batch = null;
                if (!empty($ln['heat_number']) && !empty($ln['item_code'])) {
                    $batch = Batch::where('heat_number', $ln['heat_number'])
                        ->where('item_code', $ln['item_code'])
                        ->first();
                } elseif (!empty($ln['heat_number'])) {
                    $batch = Batch::where('heat_number', $ln['heat_number'])->latest('cast_date')->first();
                } elseif (!empty($ln['batch_code'])) {
                    $batch = Batch::where('batch_code', $ln['batch_code'])->first();
                }

                if (!$batch) {
                    // create ad-hoc batch
                    $batchCode = $ln['batch_code'] ?? Batch::generateBatchCode($data['date'] ?? Carbon::now()->toDateString());
                    $batch = Batch::create([
                        'heat_number'       => $ln['heat_number'] ?? null,
                        'item_code'         => $ln['item_code'] ?? null,
                        'item_name'         => $ln['item_name'] ?? null,
                        'weight_per_pc'     => $ln['weight_per_pc'] ?? null,
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

                $incoming = isset($ln['qty_pcs']) ? (int)$ln['qty_pcs'] : 0;

                // server-side canonical qty_kg calculation (ignore client value if provided)
                $weightPerPc = $batch->weight_per_pc ?? ($ln['weight_per_pc'] ?? 0.0);
                $qtyKg = round($incoming * (float)$weightPerPc, 3);

                // category and variant handling
                $categoryId = $ln['defect_category_id'] ?? $ln['defect_type_id'] ?? null; // accept either
                $variantId = $ln['variant_id'] ?? null;

                if (!$variantId && $categoryId) {
                    // find variant prioritizing department-specific, else global
                    $variant = DefectTypeVariant::where('defect_category_id', $categoryId)
                        ->where(function ($q) use ($departmentId) {
                            $q->where('department_id', $departmentId)->orWhereNull('department_id');
                        })
                        ->orderByRaw("department_id IS NULL, ordering ASC")
                        ->first();
                    $variantId = $variant?->id;
                }

                // enforce batch_qty constraint only when batch_qty > 0
                if ($batch->batch_qty > 0) {
                    $currentSum = (int) DefectLine::where('batch_id', $batch->id)->sum('qty_pcs');
                    if ($currentSum + $incoming > $batch->batch_qty) {
                        throw ValidationException::withMessages([
                            "lines.{$idx}.qty_pcs" => ["Total defect qty exceeds batch_qty for heat {$batch->heat_number} / {$batch->item_code}"]
                        ]);
                    }
                }

                DefectLine::create([
                    'defect_id'          => $defect->id,
                    'batch_id'           => $batch->id,
                    'defect_type_id'     => $ln['defect_type_id'] ?? null,
                    'subtype_id'         => $ln['subtype_id'] ?? null,
                    'defect_category_id' => $categoryId ?? null,
                    'variant_id'         => $variantId ?? null,
                    'qty_pcs'            => $incoming,
                    'qty_kg'             => $qtyKg,
                    'item_name'          => $batch->item_name ?? $ln['item_name'] ?? null,
                    'aisi'               => $batch->aisi ?? null,
                    'size'               => $batch->size ?? null,
                    'line'               => $batch->line ?? null,
                    'cust_name'          => $batch->cust_name ?? null,
                ]);
            }
        });

        return redirect()->route('defects.index')->with('status', 'Defect disimpan sebagai draft.');
    }

    /**
     * Submit, destroy, recycle, restore methods unchanged
     */
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

    /**
     * Update defect + lines
     * - Update existing lines
     * - Create new lines (auto assign variant + calculate qty_kg)
     * - Delete removed lines
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
            'lines.*.defect_category_id'=> ['nullable', 'exists:defect_categories,id'],
            'lines.*.weight_per_pc'    => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $defect, $request) {
            $defect->update($validated + ['notes' => $validated['notes'] ?? $defect->notes]);

            $existingLines = $defect->lines()->get()->keyBy('id');
            $seenIds = [];

            $departmentId = $validated['department_id'] ?? $defect->department_id;

            foreach ($validated['lines'] as $line) {
                if (!empty($line['id'])) {
                    $lineId = (int) $line['id'];
                    $seenIds[] = $lineId;

                    $dl = DefectLine::find($lineId);
                    if (!$dl) continue;

                    $incoming = isset($line['qty_pcs']) ? (int) $line['qty_pcs'] : $dl->qty_pcs;

                    // If batch exists, check constraint (excluding current line)
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

                    // server-side compute qty_kg if weight_per_pc known; otherwise preserve provided or existing
                    $weightPerPc = $batch->weight_per_pc ?? ($line['weight_per_pc'] ?? null);
                    $qtyKg = $line['qty_kg'] ?? $dl->qty_kg;
                    if ($weightPerPc !== null) {
                        $qtyKg = round($incoming * (float)$weightPerPc, 3);
                    }

                    $dl->update([
                        'defect_type_id' => $line['defect_type_id'] ?? $dl->defect_type_id,
                        'subtype_id'     => $line['subtype_id'] ?? $dl->subtype_id,
                        'qty_pcs'        => $incoming,
                        'qty_kg'         => $qtyKg,
                    ]);
                } else {
                    // new line path
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

                    // check batch capacity
                    $currentSum = (int) DefectLine::where('batch_id', $batch->id)->sum('qty_pcs');
                    if ($currentSum + $incoming > $batch->batch_qty) {
                        throw ValidationException::withMessages([
                            'lines' => ["Total defect qty exceeds batch_qty for heat {$batch->heat_number} / {$batch->item_code}"]
                        ]);
                    }

                    // compute server-side qty_kg based on batch.weight_per_pc or provided weight
                    $weightPerPc = $batch->weight_per_pc ?? ($line['weight_per_pc'] ?? 0);
                    $qtyKg = round($incoming * (float)$weightPerPc, 3);

                    // choose category and auto-assign variant (prefer department-specific)
                    $categoryId = $line['defect_category_id'] ?? $line['defect_type_id'] ?? null;
                    $variantId = $line['variant_id'] ?? null;
                    if (!$variantId && $categoryId) {
                        $variant = DefectTypeVariant::where('defect_category_id', $categoryId)
                            ->where(function ($q) use ($departmentId) {
                                $q->where('department_id', $departmentId)->orWhereNull('department_id');
                            })
                            ->orderByRaw("department_id IS NULL, ordering ASC")
                            ->first();
                        $variantId = $variant?->id;
                    }

                    $new = DefectLine::create([
                        'defect_id'          => $defect->id,
                        'batch_id'           => $batch->id,
                        'defect_type_id'     => $line['defect_type_id'],
                        'subtype_id'         => $line['subtype_id'] ?? null,
                        'defect_category_id' => $categoryId ?? null,
                        'variant_id'         => $variantId ?? null,
                        'qty_pcs'            => $incoming,
                        'qty_kg'             => $qtyKg,
                        'item_name'          => $batch->item_name ?? null,
                        'aisi'               => $batch->aisi ?? null,
                        'size'               => $batch->size ?? null,
                        'line'               => $batch->line ?? null,
                        'cust_name'          => $batch->cust_name ?? null,
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

    /**
     * Helper: normalize lines payload from request (structured or parallel arrays)
     */
    protected function normalizeLinesFromRequest(Request $request): array
    {
        $lines = [];

        if ($request->has('lines') && is_array($request->input('lines'))) {
            foreach ($request->input('lines') as $raw) {
                if (!is_array($raw)) continue;
                $heat = trim($raw['heat_number'] ?? $raw['heat'] ?? '');
                $item = trim($raw['item_code'] ?? '');
                $pcs  = isset($raw['qty_pcs']) ? (int)$raw['qty_pcs'] : 0;
                $type = $raw['defect_type_id'] ?? null;

                if ($heat === '' && $item === '' && $pcs <= 0 && empty($type)) {
                    continue;
                }

                $lines[] = [
                    'heat_number'        => $heat !== '' ? $heat : null,
                    'item_code'          => $item !== '' ? $item : null,
                    'defect_type_id'     => $type ?: null,
                    'subtype_id'         => $raw['subtype_id'] ?? null,
                    'defect_category_id' => $raw['defect_category_id'] ?? null,
                    'variant_id'         => $raw['variant_id'] ?? null,
                    'qty_pcs'            => $pcs,
                    'qty_kg'             => isset($raw['qty_kg']) ? (float)$raw['qty_kg'] : null,
                    'batch_code'         => $raw['batch_code'] ?? null,
                    'weight_per_pc'      => isset($raw['weight_per_pc']) ? (float)$raw['weight_per_pc'] : null,
                    'item_name'          => $raw['item_name'] ?? null,
                ];
            }
        } else {
            // legacy parallel arrays: attempt to build similarly (not expected often)
            $heats      = $request->input('heat_number', []);
            $items      = $request->input('item_code', []);
            $types      = $request->input('defect_type_id', []);
            $subtypes   = $request->input('subtype_id', []);
            $qtyPcs     = $request->input('qty_pcs', []);
            $qtyKg      = $request->input('qty_kg', []);
            $batchCodes = $request->input('batch_code', []);
            $catIds     = $request->input('defect_category_id', []);
            $variantIds = $request->input('variant_id', []);
            $weights    = $request->input('weight_per_pc', []);
            $itemNames  = $request->input('item_name', []);

            $count = max(
                count($heats), count($items), count($types),
                count($subtypes), count($qtyPcs), count($qtyKg), count($batchCodes),
                count($catIds), count($variantIds), count($weights), count($itemNames)
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
                    'heat_number'        => $heat !== '' ? $heat : null,
                    'item_code'          => $item !== '' ? $item : null,
                    'defect_type_id'     => $type ?: null,
                    'subtype_id'         => $subtypes[$i] ?? null,
                    'defect_category_id' => $catIds[$i] ?? null,
                    'variant_id'         => $variantIds[$i] ?? null,
                    'qty_pcs'            => $pcs,
                    'qty_kg'             => isset($qtyKg[$i]) && $qtyKg[$i] !== '' ? (float)$qtyKg[$i] : null,
                    'batch_code'         => $batchCodes[$i] ?? null,
                    'weight_per_pc'      => isset($weights[$i]) ? (float)$weights[$i] : null,
                    'item_name'          => $itemNames[$i] ?? null,
                ];
            }
        }

        return $lines;
    }
}
