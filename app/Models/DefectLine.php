<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class DefectLine
 *
 * @package App\Models
 *
 * Fields (fillable): defect_id, batch_id, defect_type_id, subtype_id,
 * defect_category_id, variant_id, qty_pcs, qty_kg,
 * item_name, aisi, size, line, cust_name
 */
class DefectLine extends Model
{
    /**
     * Mass assignable attributes
     *
     * @var array
     */
    protected $fillable = [
        'defect_id',
        'batch_id',
        'defect_type_id',
        'subtype_id',
        'defect_category_id',
        'variant_id',
        'qty_pcs',
        'qty_kg',
        'item_name',
        'aisi',
        'size',
        'line',
        'cust_name',
    ];

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    /** Defect parent */
    public function defect()
    {
        return $this->belongsTo(Defect::class);
    }

    /** Batch parent */
    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    /** Primary defect type */
    public function type()
    {
        return $this->belongsTo(DefectType::class, 'defect_type_id');
    }

    /** Subtype (also stored in defect_types table) */
    public function subtype()
    {
        return $this->belongsTo(DefectType::class, 'subtype_id');
    }

    /** Category (new relation) */
    public function category()
    {
        return $this->belongsTo(DefectCategory::class, 'defect_category_id');
    }

    /** Variant (new relation) */
    public function variant()
    {
        return $this->belongsTo(DefectTypeVariant::class, 'variant_id');
    }

    /*
     |--------------------------------------------------------------------------
     | Backwards-compatible aliases
     |--------------------------------------------------------------------------
     | Keep these if older code expects defectType() or defectSubtype()
     */
    public function defectType()
    {
        return $this->type();
    }

    public function defectSubtype()
    {
        return $this->subtype();
    }
}
